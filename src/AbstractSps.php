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
    const STR_LOVER = [
        'sql_function' => 'lower({property})',
        'php_function' => 'strtolower',
    ];

    protected QueryBuilder $queryBuilder;

    protected AbstractCondition $condition;

    protected array $aliases = [];

    protected ?array $filterOptions = null;

    protected ?array $sortFields = null;

    protected array $sortCondition = [];

    protected string $platformName;

    public function __construct(Connection $connection)
    {
        $this->platformName = $connection->getDatabasePlatform()->getName();
        $this->queryBuilder = $connection->createQueryBuilder();
    }

    public function init(array $filters, array $sortFields = []): self
    {
        $this->initQueryBuilder();
        $this->applyFilterOptions($filters);
        $selectPart = $this->queryBuilder->getQueryPart('select');
        foreach ($selectPart as $property) {
            $fieldAndAlias = AbstractCondition::extractFieldAndAlias($property);
            $this->aliases[$fieldAndAlias['alias']] = $property;
        }
        $this->buildCondition($filters);
        $this->buildSortCondition($sortFields);

        return $this;
    }

    public function help(): array
    {
        return [
            'allowed_filters' => $this->getFilteredFields(),
            'allowed_comparison_operators' => array_keys(Rule::COMPARISON_OPERATORS),
            'allowed_sort' => $this->getSortedFields(),
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

    protected function getFilteredFields(): array
    {
        return $this->filterOptions ? array_keys($this->filterOptions) : $this->aliases;
    }

    protected function getSortedFields(): array
    {
        return $this->sortFields ?: $this->aliases;
    }

    protected function buildCondition(array $filters): void
    {
        array_walk_recursive(
            $filters,
            function (&$val, $key) {
                if ('property' === $key) {
                    if (!isset($this->aliases[$val])) {
                        throw new SpsException(sprintf('Filter "%s" does not exists', $val));
                    }
                    $val = $this->aliases[$val];
                }
            }
        );
        $this->condition = AbstractCondition::create($filters);
        $this->aliases = array_keys($this->aliases);
    }

    protected function buildSortCondition(array $sortFields): void
    {
        foreach ($sortFields as $sortField) {
            if (!is_array($sortField)) {
                $sortField = [$sortField];
            }
            $field = array_shift($sortField);
            if (!in_array($field, $this->getSortedFields())) {
                throw new SpsException(sprintf('Sort field "%s" does not exists', $field));
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
        if (null === $this->filterOptions) {
            return;
        }
        foreach ($filters as $key => &$filter) {
            if ('condition' === $key && isset($this->filterOptions[$filter['property']])) {
                $filter = array_merge($filter, $this->filterOptions[$filter['property']]);
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
