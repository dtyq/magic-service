<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Domain\MCP\Entity\ValueObject\Query;

use App\Domain\MCP\Entity\ValueObject\ServiceType;
use App\Infrastructure\Core\AbstractQuery;

class MCPServerQuery extends AbstractQuery
{
    private ?string $name = null;

    private ?ServiceType $type = null;

    private ?bool $enabled = null;

    private ?array $codes = null;

    public function getCodes(): ?array
    {
        return $this->codes;
    }

    public function setCodes(?array $codes): void
    {
        $this->codes = $codes;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getType(): ?ServiceType
    {
        return $this->type;
    }

    public function setType(?ServiceType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(?bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }
}
