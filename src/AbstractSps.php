<?php
/**
 * This file is part of the sps-dbal-component package.
 *
 * (c) Evgeniy Budanov <budanov.ua@gmail.comm> 2020.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zk2\SpsDbalComponent;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

abstract class AbstractSps
{
    protected QueryBuilder $queryBuilder;

    protected AbstractCondition $condition;

    protected array $selectPart = []; // alias => expression

    protected ?array $allowedFilterFields = null;

    protected ?array $allowedSortFields = null;

    protected array $filterOptions = [];

    protected array $sortCondition = [];

    protected array $lowerFields = []; // processing this fields by lower SQL function and strtolower PHP function

    protected string $platformName;

    public function __construct(Connection $connection)
    {
        $this->platformName = $connection->getDatabasePlatform()->getName();
        $this->queryBuilder = $connection->createQueryBuilder();
    }

    abstract public function initQueryBuilder(): self;

    public function initSps(array $filters, array $sort = []): self
    {
        if (!$this->queryBuilder->getQueryPart('select')) {
            $this->initQueryBuilder();
        }
        foreach ($this->queryBuilder->getQueryPart('select') as $property) {
            $fieldAndAlias = AbstractCondition::extractFieldAndAlias($property);
            $this->selectPart[$fieldAndAlias['alias']] = $fieldAndAlias['field'];
        }
        $this->preprocessing($filters, $sort);
        if (null === $this->allowedSortFields) {
            $this->allowedSortFields = array_keys($this->selectPart);
        }
        if (null === $this->allowedFilterFields) {
            $this->allowedFilterFields = array_fill_keys(array_keys($this->selectPart), ['operators' => array_keys(Rule::COMPARISON_OPERATORS)]);
        }
        $this->buildFilters($filters);
        $this->buildCondition($filters);
        $this->buildSortCondition($sort);

        return $this;
    }

    public function help(): array
    {
        return [
            'allowed_filters' => $this->allowedFilterFields,
            'allowed_sort' => $this->allowedSortFields,
        ];
    }

    public function getResult(int $page = 1, int $itemsOnPage = 50, bool $navigation = true, bool $totalCount = false): array
    {
        $offset = $itemsOnPage * ($page - 1);
        if ($where = $this->getWhere()) {
            $this->queryBuilder->andWhere($where);
        }
        if ($this->condition->isAggregated()) {
            $sql = $this->queryBuilder->getSQL();
            $this->queryBuilder->resetQueryParts()
                ->select('__sps_alias__.*')
                ->from(sprintf('(%s)', $sql), '__sps_alias__');
            if ($where = $this->getWhere(true)) {
                $this->queryBuilder->andWhere($where);
            }
        }
        $this->applyParameters();
        foreach ($this->sortCondition as $field => $direction) {
            $this->walkOrderBy($field, $direction);
        }
        $this->queryBuilder->setFirstResult($offset)->setMaxResults($itemsOnPage + 1);
        if (method_exists($this->queryBuilder, 'executeQuery')) {
            $data = $this->queryBuilder->executeQuery()->fetchAllAssociative();
        } else {
            $data = $this->queryBuilder->execute()->fetchAllAssociative();
        }
        $more = false;
        if (count($data) > $itemsOnPage) {
            $more = true;
            array_pop($data);
        }
        if ($navigation) {
            $result['navigation'] = [
                'items_per_page' => $itemsOnPage,
                'page' => $page,
                'more' => $more,
                'items_on_page' => count($data),
            ];
            if ($totalCount) {
                if (1 !== $page || $more) {
                    $count = $this->getTotalCount();
                } else {
                    $count = count($data);
                }
                $result['navigation']['total_items'] = $count;
                $result['navigation']['total_pages'] = (int) ceil($count / $itemsOnPage);
            }
            $result['result'] = $data;

            return $result;
        }

        return $data;
    }

    protected function getTotalCount(): int
    {
        $sql = $this->queryBuilder->resetQueryParts(['orderBy'])->setFirstResult(0)->setMaxResults(null)->getSQL();
        $stmt = $this->queryBuilder->resetQueryParts()
            ->select('count(*)')
            ->from(sprintf('(%s)', $sql), '__sps_alias__');
        if (method_exists($this->queryBuilder, 'executeQuery')) {
            return $stmt->executeQuery()->fetchOne();
        } else {
            return $stmt->execute()->fetchOne();
        }
    }

    /**
     * There is place for some validation input data
     * Also add/remove allowed filter/sort fields.
     * Also define lowerFields and custom sql/php functions for processing
     */
    protected function preprocessing(array &$filters, array &$sort): void
    {
    }

    protected function isAllowedFilterField(string $field): bool
    {
        return isset($this->allowedFilterFields[$field]);
    }

    protected function isAllowedSortField(string $field): bool
    {
        return in_array($field, $this->allowedSortFields);
    }

    protected function buildCondition(array $filters): void
    {
        $this->condition = AbstractCondition::create($filters);
    }

    protected function buildSortCondition(array $sortFields): void
    {
        foreach ($sortFields as $sortField) {
            if (!is_array($sortField)) {
                $sortField = [$sortField];
            }
            $fieldName = $field = array_shift($sortField);
            if (!$this->isAllowedSortField($fieldName)) {
                throw new SpsException(sprintf('Sort by "%s" does not allowed', $fieldName));
            }
            $direction = array_shift($sortField);
            $direction = $direction ? strtolower($direction) : 'asc';
            if (!in_array($direction, ['asc', 'desc'])) {
                throw new SpsException(sprintf('Wrong sort direction "%s"', $direction));
            }
            if (!$this->condition->isAggregated()) {
                $field = $this->selectPart[$field];
            }
            if (in_array($fieldName, $this->lowerFields)) {
                $field = sprintf('lower(%s)', $field);
            }
            $this->sortCondition[$field] = $direction;
        }
    }

    protected function walkOrderBy(string $field, string $direction): void
    {
        switch ($this->platformName) {
            case 'oracle':
            case 'postgresql':
                $direction .= ('asc' === $direction ? ' nulls first' : ' nulls last');
        }
        $this->queryBuilder->addOrderBy($field, $direction);
    }

    protected function buildFilters(array &$filters): void
    {
        foreach ($filters as &$filter) {
            if (isset($filter['property'])) {
                if (!$this->isAllowedFilterField($filter['property'])) {
                    throw new SpsException(sprintf('Filter by "%s" does not allowed', $filter['property']));
                }
                if (!in_array($filter['operator'], $this->allowedFilterFields[$filter['property']]['operators'])) {
                    throw new SpsException(sprintf('ComparisonOperator "%s" by "%s" does not allowed', $filter['operator'], $filter['property']));
                }
                $filter['name'] = $filter['property'];
                if (in_array($filter['property'], $this->lowerFields)) {
                    $filter['sql_function'] = function (string $field, Rule $rule) {
                        return sprintf('lower(%s) %s', $field, $rule->prepareOperatorAndParameter());
                    };
                    $filter['php_function'] = 'strtolower';
                }
                if (isset($this->selectPart[$filter['property']])) {
                    $filter['internal_expression'] = $this->selectPart[$filter['property']];
                }
                if (isset($this->filterOptions[$filter['property']])) {
                    $filter = array_merge($filter, $this->filterOptions[$filter['property']]);
                }
            } elseif (is_array($filter)) {
                $this->buildFilters($filter);
            }
        }
    }

    protected function getWhere(bool $forAlias = false): ?string
    {
        $where = $this->condition->buildWhere($forAlias);

        return $where ? $this->condition->trimAndOr($where) : null;
    }

    private function applyParameters(): void
    {
        $params = [];
        foreach ($this->condition->getParameters() as $param => $value) {
            $params[str_replace(':', '', $param)] = $value;
        }
        $this->queryBuilder->setParameters(
            $params,
            array_map([$this, 'inferType'], $params)
        );
    }

    /**
     * Infers type of a given value, returning a compatible constant:
     * - PDO (\PDO::PARAM*)
     * - Connection (\Doctrine\DBAL\Connection::PARAM_*)
     *
     * @param mixed $value Parameter value.
     *
     * @return mixed Parameter type constant.
     */
    private function inferType($value)
    {
        if (is_integer($value)) {
            return \PDO::PARAM_INT;
        }

        if (is_bool($value)) {
            return \PDO::PARAM_BOOL;
        }

        if (is_array($value)) {
            return is_integer(current($value))
                ? Connection::PARAM_INT_ARRAY
                : Connection::PARAM_STR_ARRAY;
        }

        return \PDO::PARAM_STR;
    }
}
