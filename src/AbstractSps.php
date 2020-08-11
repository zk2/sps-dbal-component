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

    protected array $selectPart = []; // alias => field

    protected array $allowedFilterFields = [];

    protected array $allowedSortFields = [];

    protected ?array $filterOptions = null;

    protected array $sortCondition = [];

    protected array $lowerFields = []; // processing this fields by lower SQL function and strtolower PHP function

    protected string $platformName;

    public function __construct(Connection $connection)
    {
        $this->platformName = $connection->getDatabasePlatform()->getName();
        $this->queryBuilder = $connection->createQueryBuilder();
    }

    public function init(array $filters, array $sortFields = []): self
    {
        $this->initQueryBuilder();
        $selectPart = $this->queryBuilder->getQueryPart('select');
        foreach ($selectPart as $property) {
            $fieldAndAlias = AbstractCondition::extractFieldAndAlias($property);
            $this->selectPart[$fieldAndAlias['alias']] = $fieldAndAlias['field'];
        }
        $this->allowedFilterFields = $this->allowedSortFields = array_keys($this->selectPart);
        $this->customize();

        $this->applyFilterOptions($filters);
        $this->buildCondition($filters);
        $this->buildSortCondition($sortFields);

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
        $this->queryBuilder->setParameters(
            $this->condition->getParameters(),
            array_map([$this, 'inferType'], $this->condition->getParameters())
        );
        foreach ($this->sortCondition as $field => $direction) {
            $this->walkOrderBy($field, $direction);
        }
        $data = $this->queryBuilder->setFirstResult($offset)->setMaxResults($itemsOnPage + 1)->execute()->fetchAll();
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
            ];
            if ($totalCount) {
                if (1 !== $page || $more) {
                    $sql = $this->queryBuilder->resetQueryParts(['orderBy'])->setFirstResult(null)->setMaxResults(null)->getSQL();
                    $stmt = $this->queryBuilder->resetQueryParts()
                        ->select('count(*)')
                        ->from(sprintf('(%s)', $sql), '__sps_alias__')
                        ->execute();
                    $count = $stmt->fetchColumn();
                } else {
                    $count = count($data);
                }
                $result['navigation']['total_items'] = $count;
                $result['navigation']['total_pages'] = (int) ceil($count / $itemsOnPage);
            }
            $result['data'] = $data;

            return $result;
        }

        return $data;
    }

    # Add/remove filter/sort fields. Also define lowerFields and custom sql/php functions for processing
    protected function customize(): void
    {
    }

    protected function isAllowedFilterField(string $field): bool
    {
        return in_array($field, $this->allowedFilterFields);
    }

    protected function isAllowedSortField(string $field): bool
    {
        return in_array($field, $this->allowedSortFields);
    }

    protected function buildCondition(array $filters): void
    {
        $this->condition = AbstractCondition::create($filters);
        $this->selectPart = array_keys($this->selectPart);
    }

    protected function buildSortCondition(array $sortFields): void
    {
        foreach ($sortFields as $sortField) {
            if (!is_array($sortField)) {
                $sortField = [$sortField];
            }
            $field = array_shift($sortField);
            if (!$this->isAllowedSortField($field)) {
                throw new SpsException(sprintf('Sort by "%s" does not allowed', $field));
            }
            $direction = array_shift($sortField);
            $direction = $direction ? strtolower($direction) : 'asc';
            if (!in_array($direction, ['asc', 'desc'])) {
                throw new SpsException(sprintf('Wrong sort direction "%s"', $direction));
            }
            $this->sortCondition[$field] = $direction;
        }
    }

    protected function walkOrderBy(string $field, string $direction): void
    {
        switch ($this->platformName) {
            case 'oracle':
            case 'postgresql':
                $direction .= ('asc' === $direction ? ' NULLS FIRST' : ' NULLS LAST');
        }
        $this->queryBuilder->addOrderBy($field, $direction);
    }

    protected function applyFilterOptions(array &$filters): void
    {
        foreach ($filters as $key => &$filter) {
            if (isset($filter['property'])) {
                if (!$this->isAllowedFilterField($filter['property'])) {
                    throw new SpsException(sprintf('Filter by "%s" does not allowed', $filter['property']));
                }
                if (in_array($filter['property'], $this->lowerFields)) {
                    $filter['sql_function'] = function (string $field, Rule $rule) {
                        return sprintf('lower(%s) %s', $field, $rule->prepareOperatorAndParameter());
                    };
                    $filter['php_function'] = 'strtolower';
                }
                $filter['internal_field'] = $this->selectPart[$filter['property']] ?? null;
                if (isset($this->filterOptions[$filter['property']])) {
                    $filter = array_merge($filter, $this->filterOptions[$filter['property']]);
                }
            } elseif (is_array($filter)) {
                $this->applyFilterOptions($filter);
            }
        }
    }

    protected function getWhere(bool $forAlias = false): ?string
    {
        $where = $this->condition->buildWhere($forAlias);

        return $where ? $this->condition->trimAndOr($where) : null;
    }

    abstract protected function initQueryBuilder(): QueryBuilder;

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
