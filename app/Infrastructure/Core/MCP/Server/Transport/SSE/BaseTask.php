<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Infrastructure\Core\MCP\Server\Transport\SSE;

use Hyperf\Contract\StdoutLoggerInterface;

abstract readonly class BaseTask
{
    public function __construct(
        protected ConnectionManager $connectionManager,
        protected StdoutLoggerInterface $logger
    ) {
    }

    /**
     * 执行任务
     */
    abstract public function execute(): void;

    /**
     * 获取所有服务器名称.
     * @return array<string>
     */
    protected function getServerNames(): array
    {
        // 从ConnectionManager获取已注册的服务器名称
        return $this->connectionManager->getServerNames();
    }
}
