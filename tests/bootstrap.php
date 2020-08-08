<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

$env = new DotEnv();
$env->load(__DIR__.'/docker/.env');
unset($env);
