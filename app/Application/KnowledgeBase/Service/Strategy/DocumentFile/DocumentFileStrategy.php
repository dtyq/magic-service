<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Application\KnowledgeBase\Service\Strategy\DocumentFile;

use App\Application\KnowledgeBase\Service\Strategy\DocumentFile\Driver\Interfaces\BaseDocumentFileStrategyInterface;
use App\Application\KnowledgeBase\Service\Strategy\DocumentFile\Driver\Interfaces\ExternalFileDocumentFileStrategyInterface;
use App\Application\KnowledgeBase\Service\Strategy\DocumentFile\Driver\Interfaces\ThirdPlatformDocumentFileStrategyInterface;
use App\Domain\KnowledgeBase\Entity\ValueObject\DocType;
use App\Domain\KnowledgeBase\Entity\ValueObject\DocumentFile\DocumentFileInterface;
use App\Domain\KnowledgeBase\Entity\ValueObject\DocumentFile\ExternalDocumentFile;
use App\Domain\KnowledgeBase\Entity\ValueObject\DocumentFile\ThirdPlatformDocumentFile;
use App\Domain\KnowledgeBase\Entity\ValueObject\KnowledgeBaseDataIsolation;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

class DocumentFileStrategy
{
    protected LoggerInterface $logger;

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->get(get_class($this));
    }

    public function parseContent(KnowledgeBaseDataIsolation $dataIsolation, ?DocumentFileInterface $documentFile): string
    {
        $driver = $this->getImplement($documentFile);
        return $driver?->parseContent($dataIsolation, $documentFile) ?? '';
    }

    public function parseDocType(KnowledgeBaseDataIsolation $dataIsolation, ?DocumentFileInterface $documentFile): ?DocType
    {
        $driver = $this->getImplement($documentFile);
        return $driver?->parseDocType($dataIsolation, $documentFile);
    }

    public function parseThirdPlatformType(KnowledgeBaseDataIsolation $dataIsolation, ?DocumentFileInterface $documentFile): ?string
    {
        $driver = $this->getImplement($documentFile);
        return $driver?->parseThirdPlatformType($dataIsolation, $documentFile);
    }

    public function parseThirdFileId(KnowledgeBaseDataIsolation $dataIsolation, ?DocumentFileInterface $documentFile): ?string
    {
        $driver = $this->getImplement($documentFile);
        return $driver?->parseThirdFileId($dataIsolation, $documentFile);
    }

    /**
     * 预处理文档文件，根据文档文件类型，进行不同的处理.
     */
    public function preProcessDocumentFiles(KnowledgeBaseDataIsolation $dataIsolation, array $documentFiles): array
    {
        $driver = $this->getImplement($documentFiles[0]);
        return $driver?->preProcessDocumentFiles($dataIsolation, $documentFiles) ?? [];
    }

    public function preProcessDocumentFile(KnowledgeBaseDataIsolation $dataIsolation, DocumentFileInterface $documentFile): DocumentFileInterface
    {
        $driver = $this->getImplement($documentFile);
        return $driver?->preProcessDocumentFile($dataIsolation, $documentFile);
    }

    private function getImplement(?DocumentFileInterface $documentFile): ?BaseDocumentFileStrategyInterface
    {
        $interface = match (get_class($documentFile)) {
            ExternalDocumentFile::class => ExternalFileDocumentFileStrategyInterface::class,
            ThirdPlatformDocumentFile::class => ThirdPlatformDocumentFileStrategyInterface::class,
            default => null,
        };

        $driver = null;
        if (container()->has($interface)) {
            /** @var BaseDocumentFileStrategyInterface $driver */
            $driver = di($interface);
        }

        if ($driver && $driver->validation($documentFile)) {
            return $driver;
        }

        $this->logger->warning('没有与[' . get_class($documentFile) . ']匹配的文本解析策略！将返回空值！');
        return null;
    }
}
