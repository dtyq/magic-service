<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Domain\Agent\Event;

use App\Domain\Agent\Entity\MagicAgentEntity;

class MagicAgentSavedEvent
{
    public function __construct(public MagicAgentEntity $agentEntity, public bool $create)
    {
    }
}
