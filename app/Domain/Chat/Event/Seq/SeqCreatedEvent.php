<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Domain\Chat\Event\Seq;

use App\Domain\Chat\Entity\ValueObject\MessagePriority;
use App\Domain\Chat\Event\ChatEventInterface;
use App\Infrastructure\Core\AbstractEvent;

class SeqCreatedEvent extends AbstractEvent implements ChatEventInterface
{
    protected array $seqIds = [];

    protected MessagePriority $seqPriority;

    public function __construct(array $seqIds)
    {
        $this->seqIds = $seqIds;
    }

    public function getSeqIds(): array
    {
        return $this->seqIds;
    }

    public function setSeqIds(array $seqIds): void
    {
        $this->seqIds = $seqIds;
    }

    public function getPriority(): MessagePriority
    {
        return $this->seqPriority;
    }

    public function setPriority(MessagePriority $priority): void
    {
        $this->seqPriority = $priority;
    }
}
