<?php

namespace Zk2\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver;
use Doctrine\DBAL\Query\QueryBuilder;

class MysqlDriverTest extends AbstractDriverTest
{
    const LOG_FILE = __DIR__.'/logs/mysql.log';

    protected ?Connection $connection = null;

    protected ?QueryBuilder $queryBuilder = null;

    protected array $config = [];

    protected int $debug = 0;

    protected function setUp(): void
    {
        $this->driver = new Driver();
        $this->config = [
            'driver' => 'pdo_mysql',
            'host' => 'mysql',
            'port' => '3306',
            'user' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASSWORD'],
            'dbname' => $_ENV['DB_NAME'],
        ];
        parent::setUp();
    }
}
