<?php

declare(strict_types=1);
use Hyperf\Contract\ApplicationInterface;
use Hyperf\Di\ClassLoader;
use Hyperf\Engine\DefaultOption;

ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');

error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');

// Tests must never touch the dev database. Set before the container boots:
// Dotenv is immutable and will not override values already present.
putenv('DB_DATABASE=paylite_test');
$_ENV['DB_DATABASE'] = 'paylite_test';
$_SERVER['DB_DATABASE'] = 'paylite_test';

// Same isolation for RabbitMQ: the dev server actively consumes the queues
// on the default vhost and would steal messages published by the suite.
putenv('AMQP_VHOST=testing');
$_ENV['AMQP_VHOST'] = 'testing';
$_SERVER['AMQP_VHOST'] = 'testing';

!defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require BASE_PATH . '/vendor/autoload.php';

!defined('SWOOLE_HOOK_FLAGS') && define('SWOOLE_HOOK_FLAGS', DefaultOption::hookFlags());

ClassLoader::init();

$container = require BASE_PATH . '/config/container.php';

$container->get(ApplicationInterface::class);
