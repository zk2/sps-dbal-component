<?php

namespace Zk2\Tests;

use Zk2\SpsDbalComponent\AbstractSps;

class SpsCountry extends AbstractSps
{
    protected function preprocessing(array &$filters, array &$sort): void
    {
        $this->lowerFields = [
            'country_name',
            'continent_name',
            'region_name',
            'capital_name',
        ];
        $this->filterOptions['city_cnt']['aggregated'] = true;
    }

    public function initQueryBuilder(): self
    {
        $this->selectFields = [
            'country.id AS id',
            'country.name AS country_name',
            'continent.name AS continent_name',
            'region.name AS region_name',
            'capital.name AS capital_name',
            'capital.last_date AS capital_last_date',
            'COUNT(city.id) AS city_cnt',
        ];
        $this->queryBuilder
            ->add('select', $this->selectFields)
            ->from('country', 'country')
            ->leftJoin('country', 'continent', 'continent', 'country.continent_id = continent.id')
            ->leftJoin('country', 'region', 'region', 'country.region_id = region.id')
            ->leftJoin('country', 'city', 'capital', 'country.capital_city_id = capital.id')
            ->leftJoin('country', 'city', 'city', 'city.country_id = country.id')
            ->groupBy('country.id')
            ->addGroupBy('continent.id')
            ->addGroupBy('region.id')
            ->addGroupBy('capital.id');

        return $this;
    }
}
