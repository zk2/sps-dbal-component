<?php

namespace Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDOSqlite\Driver;
use Doctrine\DBAL\Query\QueryBuilder;

class SqliteDriverTest extends AbstractDriverTest
{
    const LOG_FILE = __DIR__.'/logs/sqlite.log';

    protected ?Connection $connection = null;

    protected ?QueryBuilder $queryBuilder = null;

    protected array $config = [];

    protected int $debug = 0;

    protected function setUp(): void
    {
        $this->driver = new Driver();
        $this->config = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];
        parent::setUp();
    }
}
