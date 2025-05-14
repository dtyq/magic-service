<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Infrastructure\Core\MCP\Server\Transport\SSE;

use Hyperf\Crontab\Annotation\Crontab;

#[Crontab(rule: '0 */5 * * * *', name: 'MCPCleanupTask', callback: 'execute', memo: 'MCP空闲连接清理任务')]
readonly class CleanupTask extends BaseTask
{
    /**
     * 清理空闲连接.
     */
    public function execute(): void
    {
        $servers = $this->getServerNames();

        if (empty($servers)) {
            $this->logger->info('MCPCleanupNoActiveServers');
            return;
        }

        $before = [];

        // 记录清理前的连接数
        foreach ($servers as $serverName) {
            $before[$serverName] = $this->connectionManager->getConnectionCount($serverName);
        }

        // 清理连接
        $this->connectionManager->cleanupIdleConnections();

        // 记录清理后的连接数
        foreach ($servers as $serverName) {
            $after = $this->connectionManager->getConnectionCount($serverName);
            $removed = $before[$serverName] - $after;

            if ($removed > 0) {
                $this->logger->info('MCPCleanupConnectionsRemoved', [
                    'removed' => $removed,
                    'server_name' => $serverName,
                ]);
            }
        }
    }
}
