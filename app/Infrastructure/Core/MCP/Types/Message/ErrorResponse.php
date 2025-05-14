<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Infrastructure\Core\MCP\Types\Message;

use Throwable;

class ErrorResponse implements MessageInterface
{
    public function __construct(
        public int $id,
        public string $jsonrpc,
        public Throwable $throwable
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getMethod(): string
    {
        return '';
    }

    public function getJsonRpc(): string
    {
        return $this->jsonrpc;
    }

    public function getParams(): ?array
    {
        return null;
    }
}
