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

class Rule extends AbstractCondition
{
    const EQUALS = '=';
    const NOT_EQUALS = '!=';
    const GREATER_THAN = '>';
    const GREATER_THAN_OR_EQUAL = '>=';
    const LESS_THAN = '<';
    const LESS_THAN_OR_EQUAL = '<=';
    const IS_NULL = 'IS NULL';
    const IS_NOT_NULL = 'IS NOT NULL';
    const IN = 'IN';
    const NOT_IN = 'NOT IN';
    const LIKE = 'LIKE';
    const NOT_LIKE = 'NOT LIKE';
    const BETWEEN = 'BETWEEN';
    const NOT_BETWEEN = 'NOT BETWEEN';

    const TOKEN_EQUALS = 'equals';
    const TOKEN_NOT_EQUALS = 'not_equals';
    const TOKEN_GREATER_THAN = 'greater_than';
    const TOKEN_GREATER_THAN_OR_EQUAL = 'greater_than_or_equal';
    const TOKEN_LESS_THAN = 'less_than';
    const TOKEN_LESS_THAN_OR_EQUAL = 'less_than_or_equal';
    const TOKEN_IS_NULL = 'is_null';
    const TOKEN_IS_NOT_NULL = 'is_not_null';
    const TOKEN_IN = 'in';
    const TOKEN_NOT_IN = 'not_in';
    const TOKEN_BEGINS_WITH = 'begins_with';
    const TOKEN_ENDS_WITH = 'ends_with';
    const TOKEN_CONTAINS = 'contains';
    const TOKEN_NOT_BEGINS_WITH = 'not_begins_with';
    const TOKEN_NOT_ENDS_WITH = 'not_ends_with';
    const TOKEN_NOT_CONTAINS = 'not_contains';
    const TOKEN_BETWEEN = 'between';
    const TOKEN_NOT_BETWEEN = 'not_between';

    const COMPARISON_OPERATORS = [
        self::TOKEN_EQUALS => self::EQUALS,
        self::TOKEN_NOT_EQUALS => self::NOT_EQUALS,
        self::TOKEN_GREATER_THAN => self::GREATER_THAN,
        self::TOKEN_GREATER_THAN_OR_EQUAL => self::GREATER_THAN_OR_EQUAL,
        self::TOKEN_LESS_THAN => self::LESS_THAN,
        self::TOKEN_LESS_THAN_OR_EQUAL => self::LESS_THAN_OR_EQUAL,
        self::TOKEN_IS_NULL => self::IS_NULL,
        self::TOKEN_IS_NOT_NULL => self::IS_NOT_NULL,
        self::TOKEN_IN => self::IN,
        self::TOKEN_NOT_IN => self::NOT_IN,
        self::TOKEN_BEGINS_WITH => self::LIKE,
        self::TOKEN_ENDS_WITH => self::LIKE,
        self::TOKEN_CONTAINS => self::LIKE,
        self::TOKEN_NOT_BEGINS_WITH => self::NOT_LIKE,
        self::TOKEN_NOT_ENDS_WITH => self::NOT_LIKE,
        self::TOKEN_NOT_CONTAINS => self::NOT_LIKE,
        self::TOKEN_BETWEEN => self::BETWEEN,
        self::TOKEN_NOT_BETWEEN => self::NOT_BETWEEN,
    ];

    private ?string $internalField = null;

    private ?string $alias = null;

    private ?string $comparisonOperator = null;

    /** @var mixed|null  */
    private $value = null;

    private bool $isAggregated = false;

    private ?\Closure $sqlFunctionBuilder = null;

    /** @var callable|null  */
    private $phpFunction = null;

    private array $parameters = [];

    public function buildWhere(bool $external = false): ?string
    {
        if ((null === $this->internalField && null === $this->alias) || (!$external && $this->isAggregated)) {
            return null;
        }

        $field = $external ? $this->alias : $this->internalField;

        if ($this->sqlFunctionBuilder) {
            return $this->applySqlFunction($field);
        }

        if (in_array($this->comparisonOperator, [self::TOKEN_IS_NULL, self::TOKEN_IS_NOT_NULL])) {
            $this->parameters = [];
            return sprintf('%s %s %s ', $this->boolOperator, $field, $this->extractComparisonOperator());
        }

        if (null === $this->value) {
            return null;
        }

        return sprintf('%s %s %s ', $this->boolOperator, $field, $this->prepareOperatorAndParameter());
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function isAggregated(): bool
    {
        return $this->isAggregated;
    }

    public function prepareOperatorAndParameter()
    {
        $this->prepareValues();
        switch ($this->comparisonOperator) {
            case self::TOKEN_BETWEEN:
            case self::TOKEN_NOT_BETWEEN:
                return sprintf(' %s %s', $this->extractComparisonOperator(), implode(' AND ', array_keys($this->parameters)));
            case self::TOKEN_IN:
            case self::TOKEN_NOT_IN:
                return sprintf(' %s(%s)', $this->extractComparisonOperator(), key($this->parameters));
            default:
                return sprintf(' %s %s', $this->extractComparisonOperator(), key($this->parameters));
        }
    }

    public function getComparisonOperator(): ?string
    {
        return $this->comparisonOperator;
    }

    /**
     * @return mixed|null
     */
    public function getValue()
    {
        return $this->value;
    }

    protected function init(array $data)
    {
        $data = $data['condition'] ?? $data;

        $this->setInternalField($data['internal_field'] ?? null)
            ->setAlias($data['property'] ?? null)
            ->setComparisonOperator($data['comparison_operator'] ?? null)
            ->setValue($data['value'] ?? null)
            ->setPhpFunction($data['php_function'] ?? null)
            ->setSqlFunction($data['sql_function'] ?? null)
            ->setIsAggregated($data['aggregated'] ?? false);

        if (in_array($this->value, [null, []], true) && !in_array($this->comparisonOperator, [self::TOKEN_IS_NULL, self::TOKEN_IS_NOT_NULL])) {
            return;
        }

        if ($this->phpFunction) {
            try {
                if (is_array($this->value)) {
                    $this->value = array_map($this->phpFunction, $this->value);
                } else {
                    $this->value = call_user_func($this->phpFunction, $this->value);
                }
            } catch (\Exception $e) {
                throw new SpsException($e->getMessage());
            }
        }

        $parameterName = strtolower(':'.str_replace(['(', ')', '"', ',', ':', '.'], ['', '', '', '_', '_', '_'], $this->internalField).'_'.$this->sequentialNumber);

        if (in_array($this->comparisonOperator, [self::TOKEN_BETWEEN, self::TOKEN_NOT_BETWEEN])) {
            if (!is_array($this->value) || 2 !== count($this->value)) {
                throw new SpsException('ComparisonOperator is "BETWEEN". The value must contain an array of two elements');
            }
            $i = 0;
            foreach ($this->value as $subValue) {
                $this->parameters[$parameterName.'_'.$i] = $subValue;
                $i++;
            }
        } else {
            $this->parameters[$parameterName] = $this->value;
        }
    }

    private function setInternalField(?string $internalField): self
    {
        if (null !== $internalField) {
            $this->internalField = $internalField;
        }

        return $this;
    }

    private function setAlias(?string $alias): self
    {
        if (null !== $alias) {
            $this->alias = $alias;
        }

        return $this;
    }

    private function setComparisonOperator(?string $operator): self
    {
        if (null !== $operator) {
            $operator = trim($operator);
            if (!isset(self::COMPARISON_OPERATORS[$operator])) {
                throw new SpsException(sprintf('Comparison operator "%s" not supported', $operator));
            }
            $this->comparisonOperator = $operator;
        }

        return $this;
    }

    private function setValue($value): self
    {
        $this->value = $value;

        return $this;
    }

    private function setSqlFunction(?\Closure $sqlFunction): self
    {
        if (null !== $sqlFunction) {
            $this->sqlFunctionBuilder = \Closure::bind($sqlFunction, $this, get_class());
        }

        return $this;
    }

    private function setIsAggregated($bool): self
    {
        if (!is_bool($bool)) {
            $bool = in_array($bool, ['true', 'TRUE', 'on', 'ON', '1']);
        }
        $this->isAggregated = $bool;

        return $this;
    }

    private function setPhpFunction(?callable $phpFunction): self
    {
        if (null !== $phpFunction) {
            $this->phpFunction = $phpFunction;
        }

        return $this;
    }

    private function extractComparisonOperator(): string
    {
        return self::COMPARISON_OPERATORS[$this->comparisonOperator];
    }

    private function prepareValues(): void
    {
        switch ($this->comparisonOperator) {
            case self::TOKEN_BEGINS_WITH:
            case self::TOKEN_NOT_BEGINS_WITH:
                $this->parameters = array_map(function ($val) {
                    return "$val%";
                }, $this->parameters);
                return;
            case self::TOKEN_ENDS_WITH:
            case self::TOKEN_NOT_ENDS_WITH:
                $this->parameters = array_map(function ($val) {
                    return "%$val";
                }, $this->parameters);
                return;
            case self::TOKEN_CONTAINS:
            case self::TOKEN_NOT_CONTAINS:
                $this->parameters = array_map(function ($val) {
                    return "%$val%";
                }, $this->parameters);
                return;
        }
    }

    private function applySqlFunction(string $field): string
    {
        $sql = call_user_func_array($this->sqlFunctionBuilder, ['field' => $field, 'rule' => $this]);

        return sprintf('%s %s ', $this->boolOperator, $sql);
    }
}
