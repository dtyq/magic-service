<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Domain\MCP\Entity\ValueObject;

enum ServiceType: string
{
    case SSE = 'sse';
    case STDIO = 'stdio';

    public function getLabel(): string
    {
        return match ($this) {
            self::SSE => 'SSE',
            self::STDIO => 'STDIO',
        };
    }
}
