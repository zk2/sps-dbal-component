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

use Doctrine\Common\Collections\ArrayCollection;

class Condition extends AbstractCondition
{
    /** @var ArrayCollection|Rule[] */
    private ArrayCollection|array $rules;

    public function __construct(int $sequentialNumber, array $data)
    {
        $this->rules = new ArrayCollection();
        parent::__construct($sequentialNumber, $data);
    }

    public function buildWhere(bool $external = false): ?string
    {
        $where = '';
        foreach ($this->rules as $rule) {
            $where .= $rule->buildWhere($external);
        }
        $where = $this->trimAndOr($where);

        return $where ? sprintf('%s (%s)', $this->boolOperator, $where) : null;
    }

    public function isAggregated(): bool
    {
        foreach ($this->rules as $rule) {
            if ($rule->isAggregated()) {
                return true;
            }
        }

        return false;
    }

    public function getParameters(): array
    {
        $parameters = [];
        foreach ($this->rules as $rule) {
            $parameters = array_merge($parameters, $rule->getParameters());
        }

        return $parameters;
    }

    protected function init(array $data): void
    {
        foreach ($data['collection'] as $key => $item) {
            if (is_array($item) && is_numeric($key)) {
                $this->rules->add(parent::create($item, $this->sequentialNumber + $key + 1));
            }
        }
    }
}
