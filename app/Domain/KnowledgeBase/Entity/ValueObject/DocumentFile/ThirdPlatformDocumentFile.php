<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Domain\KnowledgeBase\Entity\ValueObject\DocumentFile;

use App\Domain\KnowledgeBase\Entity\ValueObject\DocType;

class ThirdPlatformDocumentFile extends AbstractDocumentFile
{
    public DocumentFileType $type = DocumentFileType::THIRD_PLATFORM;

    public string $thirdFileId;

    public string $thirdPlatformType;

    public function getThirdFileId(): string
    {
        return $this->thirdFileId;
    }

    public function setThirdFileId(string $thirdFileId): static
    {
        $this->thirdFileId = $thirdFileId;
        return $this;
    }

    public function getThirdPlatformType(): string
    {
        return $this->thirdPlatformType;
    }

    public function setThirdPlatformType(string $thirdPlatformType): static
    {
        $this->thirdPlatformType = $thirdPlatformType;
        return $this;
    }

    public function getDocType(): DocType
    {
        return DocType::TXT;
    }
}
