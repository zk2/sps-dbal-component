Quick start
===========

You need to make Sps class extends Zk2\SpsDbalComponent\AbstractSps:

.. code-block:: php

    namespace Your\Namespace;

    use Doctrine\DBAL\Query\QueryBuilder;
    use Zk2\SpsDbalComponent\AbstractSps;

    class SpsCountry extends AbstractSps
    {
        protected function customize(): void
        {
            // define city_cnt as aggregated value
            $this->filterOptions['city_cnt']['aggregated'] = true;
        }

        // define Query Builder
        // !!! IMPORTANT !!! EACH field in SELECT part should be single element in array
        protected function initQueryBuilder(): QueryBuilder
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

Imagine we are getting the following request:

.. code-block:: php

    [
      {
        "navigation": {
          "page": 1,
          "itemsOnPage": 20
        }
      },
      {
        "sort": [
          ['country_name'],
          ['city_cnt', 'desc']
        ]
      },
      {
        "filter": {
          "collection": [
            {
              "bool_operator": null,
              "condition": {
                "property": "region_name",
                "operator": "in",
                "value": ["south america", "australia and new zealand"]
              }
            },
            {
              "bool_operator": "and",
              "collection": [
                {
                  "bool_operator": null,
                  "condition": {
                    "property": "capital_last_date",
                    "operator": "between",
                    "value": ["1987-05-09", "2000-01-01"]
                  }
                },
                {
                  "bool_operator": "or",
                  "condition": {
                    "property": "country_name",
                    "operator": "contains",
                    "value": "islands"
                  }
                }
              ]
            },
            {
              "bool_operator": "and",
              "collection": [
                {
                  "bool_operator": null,
                  "condition": {
                    "property": "city_cnt",
                    "operator": "greater_than",
                    "value": 3
                  }
                }
              ]
            }
          ]
        }
      }
    ]

We can pass it to our SpsCountry class as:

.. code-block:: php

    $decodedRequest = json_decode($request, true);
    /** @var \Doctrine\DBAL\Connection $connection */
    $sps = new SpsCountry($connection);
    $sps->init($decodedRequest['filter'], $decodedRequest['sort']);
    $data = $sps->getResult($decodedRequest['navigation']['page'], $decodedRequest['navigation']['itemsOnPage']);

It will make pseudo-SQL like this

.. code-block:: sql

    SELECT __sps_alias__.* FROM (
        SELECT country.id AS id,
            country.name AS country_name,
            continent.name AS continent_name,
            region.name AS region_name,
            capital.name AS capital_name,
            COUNT(city.id) AS city_cnt
        FROM country country
        LEFT JOIN continent continent ON country.continent_id = continent.id
        LEFT JOIN region region ON country.region_id = region.id
        LEFT JOIN city capital ON country.capital_city_id = capital.id
        LEFT JOIN city city ON city.country_id = country.id
        WHERE (lower(region.name)  IN(:region_name_1)
            and (capital.last_date  BETWEEN :capital_last_date_3_0 AND :capital_last_date_3_1 or lower(country.name)  LIKE :country_name_4 ))
        GROUP BY country.id, continent.id, region.id, capital.id
    ) __sps_alias__
    WHERE city_cnt  > :countcity_id_4 LIMIT 20
