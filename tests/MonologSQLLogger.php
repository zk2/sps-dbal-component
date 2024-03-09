<?php
namespace Zk2\Tests;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Doctrine\DBAL\Logging\SQLLogger;

class MonologSQLLogger implements SQLLogger
{
    private ?Logger $logger;
    
    protected float $startTime = 0;

    public function __construct(string $fileName)
    {
        $this->logger = new Logger('doctrine');
        $this->logger->pushHandler(new StreamHandler($fileName));
    }

    public function startQuery($sql, array $params = null, array $types = null): void
    {
        $this->logger->debug($sql);

        if ($params) {
            $this->logger->debug(json_encode($params));
        }

        if ($types) {
            $this->logger->debug(json_encode($types));
        }
        
        $this->startTime = microtime(true);
    }

    public function stopQuery(): void
    {
        $ms = round(((microtime(true) - $this->startTime) * 1000));
        $this->logger->debug("Query took {$ms}ms.");
    }
}
