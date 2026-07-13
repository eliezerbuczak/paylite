<?php

declare(strict_types=1);
use App\Controller\UserController;
use App\Controller\WalletController;
use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'POST', 'HEAD'], '/', 'App\Controller\IndexController@index');

Router::post('/users', [UserController::class, 'store']);

Router::post('/wallets/{userId:\d+}/deposits', [WalletController::class, 'deposit']);

Router::get('/favicon.ico', function () {
    return '';
});
