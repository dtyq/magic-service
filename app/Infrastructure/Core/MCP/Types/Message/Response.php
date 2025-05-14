<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Infrastructure\Core\MCP\Types\Message;

use JsonSerializable;

class Response implements MessageInterface, JsonSerializable
{
    public function __construct(
        public int $id,
        public string $jsonrpc,
        public array $result,
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

    public function jsonSerialize(): array
    {
        if (isset($this->result['content'])) {
            $this->result['content'] = array_map(function ($item) {
                if (isset($item['text'], $item['type']) && $item['type'] === 'text') {
                    $item['text'] = json_encode($item['text'], JSON_UNESCAPED_UNICODE);
                }
                return $item;
            }, $this->result['content']);
        }

        return [
            'id' => $this->id,
            'jsonrpc' => $this->jsonrpc,
            'result' => $this->result,
        ];
    }
}
