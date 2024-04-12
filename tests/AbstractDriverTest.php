<?php

namespace Zk2\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Configuration;
use PHPUnit\Framework\TestCase;
use Firehed\DbalLogger\Middleware;

abstract class AbstractDriverTest extends TestCase
{
    const LOG_FILE = null;

    protected ?Connection $connection = null;

    protected ?QueryBuilder $queryBuilder = null;

    protected array $config = [];

    protected bool $reloadAllData = false;

    protected int $debug = 0;

    protected function setUp(): void
    {
        if (in_array('-v', $_SERVER['argv'], true)) {
            $this->debug = 1;
        } elseif (in_array('-vv', $_SERVER['argv'], true)) {
            $this->debug = 2;
        } elseif (in_array('-vvv', $_SERVER['argv'], true)) {
            $this->debug = 3;
        }
        if (in_array('-vvvv', $_SERVER['argv'], true)) {
            $this->reloadAllData = true;
        }

        try {
            $conf = new Configuration();
            $logger = new MonologSQLLogger(static::LOG_FILE);
            $conf->setMiddlewares([new Middleware($logger)]);
            $this->connection = DriverManager::getConnection($this->config, $conf);
            $this->queryBuilder = new QueryBuilder($this->connection);
        } catch (\Exception $e) {
            $this->markTestSkipped('Skipped...'.PHP_EOL.$e->getMessage());
        }
        $this->loadData();
    }

    public function testConnectionTest()
    {
        $this->assertEquals('Hello', $this->connection->executeQuery("SELECT 'Hello'")->fetchOne());
    }

    /**
     * @dataProvider dataProvider
     */
    public function testQueryTest(array $filter, ?array $sort = null, int $page = 1, int $itemsOnPage = 50, ?bool $navigation = true, ?bool $totalCount = false)
    {
        $sps = new SpsCountry($this->connection);
        $data = $sps->initSps($filter, $sort)->getResult($page, $itemsOnPage, $navigation, $totalCount);
        $this->assertArrayHasKey('navigation', $data);
        $this->assertArrayHasKey('result', $data);
    }

    public function dataProvider(): array
    {
        $filter = [
            'collection' => [
                [
                    'bool_operator' => null,
                    'condition' => [
                        'property' => 'region_name',
                        'operator' => 'in',
                        'value' => ['south america', 'australia and new zealand'],
                    ],
                ],
                [
                    'bool_operator' => 'and',
                    'collection' => [
                        [
                            'bool_operator' => null,
                            'condition' => [
                                'property' => 'capital_last_date',
                                'operator' => 'between',
                                'value' => ['1987-05-09', '2000-01-01'],
                            ],
                        ],
                        [
                            'bool_operator' => 'or',
                            'condition' => [
                                'property' => 'country_name',
                                'operator' => 'contains',
                                'value' => 'islands',
                            ],
                        ],
                    ],
                ],
                [
                    'bool_operator' => 'and',
                    'collection' => [
                        [
                            'bool_operator' => null,
                            'condition' => [
                                'property' => 'city_cnt',
                                'operator' => 'greater_than',
                                'value' => 3,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return [
            [[], []],
            [$filter, [], 1, 5],
            [$filter, [], 2, 5, true, true],
            [$filter, [['country_name'],['city_cnt', 'desc']], 1, 5],
            [$filter, [['capital_last_date', 'desc']], 1, 5],
            [$filter, [], 10, 5, true],
        ];
    }

    private function loadData(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $tableRegionExists = $schemaManager->tablesExist('country_language');
        $tableContinentExists = $schemaManager->tablesExist('country_language');
        $tableCityExists = $schemaManager->tablesExist('country_language');
        $tableCountryExists = $schemaManager->tablesExist('country_language');
        $tableCountryLanguageExists = $schemaManager->tablesExist('country_language');
        if (!$this->reloadAllData && ($tableRegionExists && $tableContinentExists && $tableCityExists && $tableCountryExists && $tableCountryLanguageExists)) {
            return;
        }

        if (!file_exists(__DIR__.'/fixtures/data.php')) {
            $this->markTestSkipped('Skipped...'.PHP_EOL.'Fixtures not found');
        }

        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($tableCountryLanguageExists) {
            $this->connection->executeStatement($dbPlatform->getDropTableSQL('country_language'));
        }
        if ($tableCityExists) {
            $this->connection->executeStatement($dbPlatform->getDropTableSQL('city'));
        }
        if ($tableCountryExists) {
            $this->connection->executeStatement($dbPlatform->getDropTableSQL('country'));
        }
        if ($tableRegionExists) {
            $this->connection->executeStatement($dbPlatform->getDropTableSQL('region'));
        }
        if ($tableContinentExists) {
            $this->connection->executeStatement($dbPlatform->getDropTableSQL('continent'));
        }

        $schema = new Schema();

        $tableRegion = $schema->createTable('region');
        $tableRegion->addColumn('id', 'integer', ['unsigned' => true, 'autoincrement' => true]);
        $tableRegion->addColumn('name', 'string', ['length' => 255]);
        $tableRegion->setPrimaryKey(['id']);
        $tableRegion->addIndex(['name']);

        $tableContinent = $schema->createTable('continent');
        $tableContinent->addColumn('id', 'integer', ['unsigned' => true, 'autoincrement' => true]);
        $tableContinent->addColumn('name', 'string', ['length' => 255]);
        $tableContinent->setPrimaryKey(['id']);
        $tableContinent->addIndex(['name']);

        $tableCity = $schema->createTable('city');
        $tableCity->addColumn('id', 'integer', ['unsigned' => true, 'autoincrement' => true]);
        $tableCity->addColumn('country_id', 'integer', ['unsigned' => true]);
        $tableCity->addColumn('name', 'string', ['length' => 255]);
        $tableCity->addColumn('district', 'string', ['length' => 255]);
        $tableCity->addColumn('population', 'integer', ['default' => 0]);
        $tableCity->addColumn('last_date', 'datetime');
        $tableCity->setPrimaryKey(['id']);
        $tableCity->addIndex(['country_id', 'name']);

        $tableCountry = $schema->createTable('country');
        $tableCountry->addColumn('id', 'integer', ['unsigned' => true, 'autoincrement' => true]);
        $tableCountry->addColumn('capital_city_id', 'integer', ['unsigned' => true, 'notnull' => false]);
        $tableCountry->addColumn('continent_id', 'integer', ['unsigned' => true]);
        $tableCountry->addColumn('region_id', 'integer', ['unsigned' => true]);
        $tableCountry->addColumn('name', 'string', ['length' => 255]);
        $tableCountry->addColumn('head_of_state', 'string', ['length' => 255]);
        $tableCountry->addColumn('code', 'string', ['length' => 255]);
        $tableCountry->addColumn('code2', 'string', ['length' => 255]);
        $tableCountry->addColumn('indep_year', 'integer', ['notnull' => false]);
        $tableCountry->addColumn('surface_area', 'decimal', ['precision' => 10, 'scale' => 2]);
        $tableCountry->addColumn('gnp', 'decimal', ['precision' => 10, 'scale' => 2]);
        $tableCountry->addColumn('gnp_old', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $tableCountry->addColumn('population', 'integer', ['unsigned' => true]);
        $tableCountry->addColumn('life_expectancy', 'decimal', ['precision' => 3, 'scale' => 1]);
        $tableCountry->addColumn('local_name', 'string', ['length' => 255, 'notnull' => false]);
        $tableCountry->addColumn('government_form', 'string', ['length' => 255, 'notnull' => false]);
        $tableCountry->addColumn('last_date', 'datetime');
        $tableCountry->addColumn('is_green', 'boolean', ['default' => false]);

        $tableCountry->setPrimaryKey(['id']);
        $tableCountry->addIndex(['capital_city_id', 'continent_id', 'region_id', 'name', 'code']);
        $tableCountry->addForeignKeyConstraint($tableRegion, ['region_id'], ['id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
        $tableCountry->addForeignKeyConstraint($tableContinent, ['continent_id'], ['id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
        $tableCity->addForeignKeyConstraint($tableCountry, ['country_id'], ['id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);

        $tableCountryLanguage = $schema->createTable('country_language');
        $tableCountryLanguage->addColumn('id', 'integer', ['unsigned' => true, 'autoincrement' => true]);
        $tableCountryLanguage->addColumn('country_id', 'integer', ['unsigned' => true]);
        $tableCountryLanguage->addColumn('lang', 'string', ['length' => 255]);
        $tableCountryLanguage->addColumn('is_official', 'boolean', ['default' => false]);
        $tableCountryLanguage->addColumn('percentage', 'decimal', ['precision' => 4, 'scale' => 1, 'default' => '0.0']);
        $tableCountryLanguage->setPrimaryKey(['id']);
        $tableCountryLanguage->addIndex(['country_id', 'lang']);
        $tableCountryLanguage->addForeignKeyConstraint($tableCountry, ['country_id'], ['id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);

        $queries = $schema->toSql($dbPlatform);

        foreach ($queries as $query) {
            $this->connection->executeStatement($query);
        }

        $regions = $continents = $countries = $countryLanguages = $cities = [];
        require __DIR__.'/fixtures/data.php';

        foreach ($regions as $regionData) {
            $this->connection->insert('region', $regionData);
        }

        foreach ($continents as $continentData) {
            $this->connection->insert('continent', $continentData);
        }

        foreach ($countries as $num => $countryData) {
            $countryData['is_green'] = (bool)!($num & 1);
            $this->connection->insert('country', $countryData, ['is_green' => \PDO::PARAM_BOOL]);
        }

        foreach ($cities as $cityData) {
            $this->connection->insert('city', $cityData);
        }

        foreach ($countryLanguages as $countryLanguagesData) {
            $this->connection->insert('country_language', $countryLanguagesData);
        }

        foreach ($this->connection->executeQuery(/** @lang text */ 'SELECT id, code FROM country')->fetchAllAssociative() as $country) {
            $arr = explode(' ', $country['code']);
            if (!isset($arr[1]) or !$arr[1]) {
                continue;
            }
            $this->connection->update('country', ['capital_city_id' => $arr[1], 'code' => trim($arr[0])], $country['id']);
        }
    }
}
