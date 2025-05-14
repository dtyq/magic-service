<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Infrastructure\Core\MCP\Server\Transport\SSE;

use App\Infrastructure\Core\MCP\Types\Message\Notification;
use Hyperf\Engine\Http\EventStream;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Throwable;

class ConnectionManager
{
    /**
     * 按服务器名称组织的连接列表.
     * @var array<string, array<int, EventStream>>
     */
    private array $connections = [];

    /**
     * 会话ID到连接FD的映射.
     * @var array<string, array<string, int>>
     */
    private array $sessionMaps = [];

    /**
     * 连接最后活跃时间.
     * @var array<string, array<int, int>>
     */
    private array $lastActiveTime = [];

    /**
     * 连接超时时间（秒）.
     */
    private int $timeout = 300;

    private LoggerInterface $logger;

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->get('ConnectionManager');
    }

    /**
     * 获取已注册的所有服务器名称.
     * @return array<string>
     */
    public function getServerNames(): array
    {
        return array_keys($this->connections);
    }

    /**
     * 注册新连接.
     */
    public function registerConnection(string $serverName, string $sessionId, int $fd, EventStream $connection): void
    {
        if (! isset($this->connections[$serverName])) {
            $this->connections[$serverName] = [];
            $this->sessionMaps[$serverName] = [];
            $this->lastActiveTime[$serverName] = [];
        }

        // 检查是否存在相同fd的连接，如果有，先移除旧连接
        if (isset($this->connections[$serverName][$fd])) {
            $oldSessionId = $this->getSessionId($serverName, $fd);
            if ($oldSessionId !== null && $oldSessionId !== $sessionId) {
                $this->removeConnection($serverName, $oldSessionId);
            }
        }

        $this->connections[$serverName][$fd] = $connection;
        $this->sessionMaps[$serverName][$sessionId] = $fd;
        $this->lastActiveTime[$serverName][$fd] = time();

        $this->logger->info('ConnectionRegistered', [
            'server_name' => $serverName,
            'session_id' => $sessionId,
            'fd' => $fd,
        ]);
    }

    /**
     * 移除连接.
     */
    public function removeConnection(string $serverName, string $sessionId): void
    {
        if (! isset($this->sessionMaps[$serverName][$sessionId])) {
            return;
        }

        $fd = $this->sessionMaps[$serverName][$sessionId];
        unset($this->connections[$serverName][$fd], $this->sessionMaps[$serverName][$sessionId], $this->lastActiveTime[$serverName][$fd]);

        $this->logger->info('ConnectionRemoved', [
            'server_name' => $serverName,
            'session_id' => $sessionId,
            'fd' => $fd,
        ]);
    }

    /**
     * 获取连接.
     */
    public function getConnection(string $serverName, string $sessionId): ?EventStream
    {
        if (! isset($this->sessionMaps[$serverName][$sessionId])) {
            return null;
        }

        $fd = $this->sessionMaps[$serverName][$sessionId];
        $this->lastActiveTime[$serverName][$fd] = time();
        return $this->connections[$serverName][$fd] ?? null;
    }

    /**
     * 根据FD获取会话ID.
     */
    public function getSessionId(string $serverName, int $fd): ?string
    {
        if (! isset($this->connections[$serverName][$fd])) {
            return null;
        }

        $sessionId = array_search($fd, $this->sessionMaps[$serverName], true);
        return $sessionId !== false ? $sessionId : null;
    }

    /**
     * 发送保活消息（替代传统心跳）.
     */
    public function sendKeepAlive(string $serverName, string $sessionId): bool
    {
        $connection = $this->getConnection($serverName, $sessionId);
        if ($connection === null) {
            return false;
        }

        try {
            // 使用MCP协议的通知消息格式
            $notification = new Notification(
                jsonrpc: '2.0',
                method: 'system/keepalive',
                params: ['timestamp' => time()]
            );
            $data = json_encode($notification);
            $connection->write("event: message\ndata: {$data}\n\n");
            return true;
        } catch (Throwable $e) {
            $this->logger->error('KeepAliveFailed', [
                'server_name' => $serverName,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
            $this->removeConnection($serverName, $sessionId);
            return false;
        }
    }

    /**
     * 获取服务器下的所有会话ID.
     * @return array<string>
     */
    public function getAllSessionIds(string $serverName): array
    {
        return array_keys($this->sessionMaps[$serverName] ?? []);
    }

    /**
     * 清理过期连接.
     */
    public function cleanupIdleConnections(): void
    {
        $now = time();
        foreach ($this->connections as $serverName => $connections) {
            foreach ($connections as $fd => $connection) {
                $lastActive = $this->lastActiveTime[$serverName][$fd] ?? 0;
                if ($now - $lastActive > $this->timeout) {
                    // 查找对应的sessionId
                    $sessionId = $this->getSessionId($serverName, $fd);
                    if ($sessionId !== null) {
                        $this->removeConnection($serverName, $sessionId);
                        $this->logger->info('IdleConnectionRemoved', [
                            'server_name' => $serverName,
                            'fd' => $fd,
                            'idle_time' => $now - $lastActive,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * 设置连接超时时间.
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * 获取连接数量.
     */
    public function getConnectionCount(string $serverName): int
    {
        return count($this->connections[$serverName] ?? []);
    }
}
