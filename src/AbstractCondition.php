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

abstract class AbstractCondition
{
    protected int $sequentialNumber = 0;

    protected string $boolOperator = 'AND';

    public static function create(array $data, int $sequentialNumber = 0): AbstractCondition
    {
        if (array_key_exists('collection', $data)) {
            return new Condition($sequentialNumber, $data);
        }

        return new Rule($sequentialNumber, $data);
    }

    public static function extractFieldAndAlias(?string $expression): array
    {
        $return = ['field' => null, 'alias' => null];
        if (null !== $expression) {
            $expression = trim(str_replace('  ', ' ', $expression));
            if (stripos($expression, ' as ') !== false) {
                $expression = str_replace([' AS ', ' aS ', ' As '], ' as ', $expression);
                $delimiter = ' as ';
            } else {
                $delimiter = ' ';
            }
            $arr = explode($delimiter, $expression);
            if (count($arr) > 2) {
                throw new SpsException(sprintf('Expression "%s" is wrong', $expression));
            }
            $return['field'] = $arr[0];
            if (isset($arr[1])) {
                $return['alias'] = $arr[1];
            } else {
                $pos = strrpos($return['field'], '.');
                $pos = false === $pos ? 0 : $pos + 1;
                $return['alias'] = substr($return['field'], $pos);
            }
        }

        return $return;
    }

    public function trimAndOr(string $condition): string
    {
        if (stripos($condition, 'and ') === 0) {
            $condition = substr($condition, 4);
        } elseif (stripos($condition, 'or ') === 0) {
            $condition = substr($condition, 3);
        }

        return $condition;
    }

    public function __construct(int $sequentialNumber, array $data)
    {
        $this->sequentialNumber = $sequentialNumber;
        if (array_key_exists('bool_operator', $data)) {
            $this->setBoolOperator($data['bool_operator']);
            unset($data['bool_operator']);
        }
        $this->init($data);
    }

    public function isAggregated(): bool
    {
        return false;
    }

    protected function setBoolOperator(?string $operator): self
    {
        if ($operator = strtolower($operator ?? '')) {
            if (!in_array($operator, ['and', 'or'])) {
                throw new SpsException('Invalid operator. Use "and" or "or"');
            }
            $this->boolOperator = $operator;
        }

        return $this;
    }

    abstract public function buildWhere(bool $external = false): ?string;

    abstract public function getParameters(): array;

    abstract protected function init(array $data);
}
