<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */
use App\Infrastructure\Util\Middleware\RequestContextMiddleware;
use App\Interfaces\ModelGateway\Facade\Admin\AccessTokenModelGatewayAdminApi;
use App\Interfaces\ModelGateway\Facade\Admin\ApplicationModelGatewayAdminApi;
use App\Interfaces\ModelGateway\Facade\Open\OpenAIProxyApi;
use Hyperf\HttpServer\Router\Router;

// OpenAI 兼容接口 - 一定是 openai 模式，不要修改这里
Router::addGroup('/v1', function () {
    Router::post('/chat/completions', [OpenAIProxyApi::class, 'chatCompletions']);
    Router::post('/embeddings', [OpenAIProxyApi::class, 'embeddings']);
    Router::get('/models', [OpenAIProxyApi::class, 'models']);
});

Router::addGroup('/api/v1', static function () {
    Router::addGroup('/model-gateway', static function () {
        Router::addGroup('/access-token', static function () {
            Router::post('/queries', [AccessTokenModelGatewayAdminApi::class, 'queries']);
            Router::get('/{id}', [AccessTokenModelGatewayAdminApi::class, 'show']);
            Router::post('', [AccessTokenModelGatewayAdminApi::class, 'save']);
            Router::delete('/{id}', [AccessTokenModelGatewayAdminApi::class, 'destroy']);
        });

        Router::addGroup('/application', function () {
            Router::post('/queries', [ApplicationModelGatewayAdminApi::class, 'queries']);
            Router::post('', [ApplicationModelGatewayAdminApi::class, 'save']);
            Router::get('/{id}', [ApplicationModelGatewayAdminApi::class, 'show']);
            Router::delete('/{id}', [ApplicationModelGatewayAdminApi::class, 'destroy']);
        });
    }, ['middleware' => [RequestContextMiddleware::class]]);
});
