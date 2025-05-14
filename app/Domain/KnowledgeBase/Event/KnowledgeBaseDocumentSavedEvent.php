<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Domain\KnowledgeBase\Event;

use App\Domain\KnowledgeBase\Entity\KnowledgeBaseDocumentEntity;
use App\Domain\KnowledgeBase\Entity\KnowledgeBaseEntity;

class KnowledgeBaseDocumentSavedEvent
{
    public function __construct(
        public KnowledgeBaseEntity $knowledgeBaseEntity,
        public KnowledgeBaseDocumentEntity $knowledgeBaseDocumentEntity,
        public bool $create,
    ) {
    }
}
