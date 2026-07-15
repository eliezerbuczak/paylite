<?php

declare(strict_types=1);
use App\Transfer\Presentation\Http\TransferController;
use App\User\Presentation\Http\UserController;
use App\Wallet\Presentation\Http\WalletController;
use Hyperf\HttpServer\Router\Router;

Router::post('/users', [UserController::class, 'store']);

Router::post('/wallets/{userId:\d+}/deposits', [WalletController::class, 'deposit']);

Router::post('/transfer', [TransferController::class, 'store']);

Router::get('/favicon.ico', function () {
    return '';
});
