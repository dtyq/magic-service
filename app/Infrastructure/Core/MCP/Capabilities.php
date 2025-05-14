<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Infrastructure\Core\MCP;

use JsonSerializable;
use stdClass;

class Capabilities implements JsonSerializable
{
    public function __construct(
        protected ?bool $hasTools = null,
        protected ?bool $hasResources = null,
        protected ?bool $hasPrompts = null,
    ) {
    }

    public function jsonSerialize(): stdClass
    {
        $capabilities = new stdClass();
        if ($this->hasTools) {
            $capabilities->tools = new stdClass();
        }
        if ($this->hasResources) {
            $capabilities->resources = new stdClass();
        }
        if ($this->hasPrompts) {
            $capabilities->prompts = new stdClass();
        }
        return $capabilities;
    }
}
