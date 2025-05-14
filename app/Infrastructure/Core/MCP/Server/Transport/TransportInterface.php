<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Infrastructure\Core\MCP\Server\Transport;

use App\Infrastructure\Core\MCP\Types\Message\MessageInterface;

interface TransportInterface
{
    public function sendMessage(string $serverName, ?MessageInterface $message): void;

    public function readMessage(): MessageInterface;

    public function send(string $serverName, string $message): void;

    public function register(string $path, string $serverName): void;
}
