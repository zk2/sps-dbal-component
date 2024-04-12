<?php

namespace Zk2\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class PostgresDriverTest extends AbstractDriverTest
{
    const LOG_FILE = __DIR__.'/logs/postgresql.log';

    protected ?Connection $connection = null;

    protected ?QueryBuilder $queryBuilder = null;

    protected array $config = [];

    protected int $debug = 0;

    protected function setUp(): void
    {
        $this->config = [
            'driver' => 'pdo_pgsql',
            'host' => 'pgsql',
            'port' => '5432',
            'user' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASSWORD'],
            'dbname' => $_ENV['DB_NAME'],
        ];
        parent::setUp();
    }
}
