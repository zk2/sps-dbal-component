Quick start
===========

You need to make Sps class extends Zk2\SpsDbalComponent\AbstractSps:

.. code-block:: php

    namespace Your\Namespace;

    use Doctrine\DBAL\Query\QueryBuilder;
    use Zk2\SpsDbalComponent\AbstractSps;

    class SpsCountry extends AbstractSps
    {
        // optional :: define options for filter. If not defined - it will be `$this->queryBuilder->getQueryPart('select')`
        protected ?array $filterOptions = [
            'id' => [],
            'country_name' => self::STR_LOVER,
            'continent_name' => self::STR_LOVER,
            'region_name' => self::STR_LOVER,
            'capital_name' => self::STR_LOVER,
            'city_cnt' => ['aggregated' => true],
        ];

        // optional :: define fields, which can be ordered. If not defined - it will be `$this->queryBuilder->getQueryPart('select')`
        protected ?array $sortFields = [
            'id',
            'country_name',
            'continent_name',
            'region_name',
            'capital_name',
            'city_cnt',
        ];

        // define Query Builder
        // !!! IMPORTANT !!! EACH field in SELECT part should be single element in array
        protected function initQueryBuilder(): QueryBuilder
        {
            return $this->queryBuilder
                ->resetQueryParts()
                ->select(
                [
                    'country.id AS id',
                    'country.name AS country_name',
                    'continent.name AS continent_name',
                    'region.name AS region_name',
                    'capital.name AS capital_name',
                    'COUNT(city.id) AS city_cnt',
                ]
            )
                ->from('country', 'country')
                ->leftJoin('country', 'continent', 'continent', 'country.continent_id = continent.id')
                ->leftJoin('country', 'region', 'region', 'country.region_id = region.id')
                ->leftJoin('country', 'city', 'capital', 'country.capital_city_id = capital.id')
                ->leftJoin('country', 'city', 'city', 'city.country_id = country.id')
                ->groupBy('country.id')
                ->addGroupBy('continent.id')
                ->addGroupBy('region.id')
                ->addGroupBy('capital.id');
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
                "comparison_operator": "in",
                "value": [
                  "south america",
                  "australia and new zealand"
                ]
              }
            },
            {
              "bool_operator": "and",
              "collection": [
                {
                  "bool_operator": null,
                  "condition": {
                    "property": "capital_last_date",
                    "comparison_operator": "between",
                    "value": [
                      "1987-05-09",
                      "2000-01-01"
                    ]
                  }
                },
                {
                  "bool_operator": "or",
                  "condition": {
                    "property": "country_name",
                    "comparison_operator": "contains",
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
                    "comparison_operator": "greater_than",
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
