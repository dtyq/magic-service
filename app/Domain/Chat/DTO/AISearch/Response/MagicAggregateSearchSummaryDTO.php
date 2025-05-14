<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Domain\Chat\DTO\AISearch\Response;

use App\Domain\Chat\Entity\AbstractEntity;

class MagicAggregateSearchSummaryDTO extends AbstractEntity
{
    protected string $llmResponse = '';

    protected array $searchContext = [];

    public function __construct(?array $data = [])
    {
        parent::__construct($data);
    }

    public function getLlmResponse(): string
    {
        return $this->llmResponse;
    }

    public function getSearchContext(): array
    {
        return $this->searchContext;
    }

    public function setLlmResponse(string $llmResponse): static
    {
        $this->llmResponse = $llmResponse;
        return $this;
    }

    public function setSearchContext(array $searchContext): static
    {
        $this->searchContext = $searchContext;
        return $this;
    }
}
