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

class Rule extends AbstractCondition implements RuleInterface
{
    private ?string $name = null;

    private ?string $internalExpression = null;

    private ?string $externalExpression = null;

    private ?string $comparisonOperator = null;

    /** @var mixed|null  */
    private $value = null;

    private bool $isAggregated = false;

    private bool $valuesAlreadyPrepared = false;

    private ?\Closure $sqlFunctionBuilder = null;

    /** @var callable|null  */
    private $phpFunction = null;

    private array $parameters = [];

    public function buildWhere(bool $external = false): ?string
    {
        if ((null === $this->internalExpression && null === $this->externalExpression) || (!$external && $this->isAggregated)) {
            return null;
        }

        $expression = $external ? $this->externalExpression : $this->internalExpression;

        if (!$expression) {
            return null;
        }

        if ($this->sqlFunctionBuilder) {
            return $this->applySqlFunction($expression);
        }

        if (in_array($this->comparisonOperator, [self::TOKEN_IS_NULL, self::TOKEN_IS_NOT_NULL])) {
            $this->parameters = [];
            return sprintf('%s %s %s ', $this->boolOperator, $expression, $this->extractComparisonOperator());
        }

        if (null === $this->value) {
            return null;
        }

        return sprintf('%s %s %s ', $this->boolOperator, $expression, $this->prepareOperatorAndParameter());
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

    public function getBaseParameterName(): string
    {
        return ':'.preg_replace('/[\W]/', '', $this->externalExpression).'_'.$this->sequentialNumber;
    }

    public function extractComparisonOperator(): string
    {
        return self::COMPARISON_OPERATORS[$this->comparisonOperator];
    }

    protected function init(array $data)
    {
        $data = $data['condition'] ?? $data;

        $this->name = $data['property'] ?? null;
        $this->setInternalExpression($data['internal_expression'] ?? null)
            ->setExternalExpression($data['external_expression'] ?? ($data['property'] ?? null))
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

        $parameterName = $this->getBaseParameterName();

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

    private function setInternalExpression(?string $expression): self
    {
        if (null !== $expression) {
            $this->internalExpression = $expression;
        }

        return $this;
    }

    private function setExternalExpression(?string $expression): self
    {
        if (null !== $expression) {
            $this->externalExpression = $expression;
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

    private function prepareValues(): void
    {
        if ($this->valuesAlreadyPrepared) {
            return;
        }
        switch ($this->comparisonOperator) {
            case self::TOKEN_BEGINS_WITH:
            case self::TOKEN_NOT_BEGINS_WITH:
                $this->parameters = array_map(fn($val) => "$val%", $this->parameters);
                break;
            case self::TOKEN_ENDS_WITH:
            case self::TOKEN_NOT_ENDS_WITH:
                $this->parameters = array_map(fn($val) => "%$val", $this->parameters);
                break;
            case self::TOKEN_CONTAINS:
            case self::TOKEN_NOT_CONTAINS:
                $this->parameters = array_map(fn($val) => "%$val%", $this->parameters);
                break;
        }
        $this->valuesAlreadyPrepared = true;
    }

    private function applySqlFunction(?string $field): string
    {
        $sql = call_user_func_array($this->sqlFunctionBuilder, ['field' => $field, 'rule' => $this]);

        return sprintf('%s %s ', $this->boolOperator, $sql);
    }
}
