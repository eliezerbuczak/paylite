<?php

declare(strict_types=1);
use App\Controller\TransferController;
use App\Controller\WalletController;
use App\User\Presentation\Http\UserController;
use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'POST', 'HEAD'], '/', 'App\Shared\Infrastructure\Http\IndexController@index');

Router::post('/users', [UserController::class, 'store']);

Router::post('/wallets/{userId:\d+}/deposits', [WalletController::class, 'deposit']);

Router::post('/transfer', [TransferController::class, 'store']);

Router::get('/favicon.ico', function () {
    return '';
});
