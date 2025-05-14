<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */
use App\Interfaces\MCP\Facade\Admin\MCPServerAdminApi;
use Hyperf\HttpServer\Router\Router;

Router::addGroup('/api/v1/mcp', function () {
    Router::addGroup('/server', function () {
        Router::post('', [MCPServerAdminApi::class, 'save']);
        Router::post('/queries', [MCPServerAdminApi::class, 'queries']);
        Router::get('/{code}', [MCPServerAdminApi::class, 'show']);
        Router::delete('/{code}', [MCPServerAdminApi::class, 'destroy']);
    });
});
