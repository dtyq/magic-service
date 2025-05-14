<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Infrastructure\Core\MCP\Server\Transport;

use App\Infrastructure\Core\MCP\Server\Transport\SSE\ConnectionManager;
use App\Infrastructure\Core\MCP\Types\Message\MessageInterface;
use App\Infrastructure\Core\MCP\Types\Message\Notification;
use App\Infrastructure\Core\MCP\Types\Message\Request;
use Hyperf\Codec\Packer\JsonPacker;
use Hyperf\Context\ApplicationContext;
use Hyperf\Engine\Http\EventStream;
use Hyperf\HttpMessage\Server\Response;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Swow\Psr7\Server\ServerConnection;

class SSETransport implements TransportInterface
{
    private LoggerInterface $logger;

    private ConnectionManager $connectionManager;

    public function __construct(
        protected RequestInterface $request,
        protected ResponseInterface $response,
        protected LoggerFactory $loggerFactory,
        protected JsonPacker $packer,
    ) {
        $this->logger = $this->loggerFactory->get('SSETransport');
        $this->connectionManager = ApplicationContext::getContainer()->get(ConnectionManager::class);
    }

    public function sendMessage(string $serverName, ?MessageInterface $message): void
    {
        if (! $message) {
            return;
        }
        $result = $this->packer->pack($message);
        $this->send($serverName, $result);
    }

    public function readMessage(): MessageInterface
    {
        $message = $this->packer->unpack($this->request->getBody()->getContents());
        if (! isset($message['id'])) {
            return new Notification(...$message);
        }
        return new Request(...$message);
    }

    public function send(string $serverName, string $message): void
    {
        $sessionId = $this->request->input('sessionId');
        $connection = $this->connectionManager->getConnection($serverName, $sessionId);

        if ($connection !== null) {
            $connection->write("event: message\ndata: {$message}\n\n");
        } else {
            $this->logger->warning('CannotSendMessage: connection not found', [
                'server_name' => $serverName,
                'session_id' => $sessionId,
            ]);
        }
    }

    public function register(string $path, string $serverName): void
    {
        /** @var Response $response */
        $response = ApplicationContext::getContainer()->get(ResponseInterface::class);
        $eventStream = new EventStream($response->getConnection(), $response);
        /** @var ServerConnection $socket */
        $socket = $response->getConnection()->getSocket();
        $fd = $socket->getFd();
        $sessionId = uniqid('sse_');

        $eventStream
            ->write('event: endpoint' . PHP_EOL)
            ->write("data: {$path}?sessionId={$sessionId}" . PHP_EOL . PHP_EOL);

        // 使用ConnectionManager注册连接
        $this->connectionManager->registerConnection($serverName, $sessionId, $fd, $eventStream);
    }
}
