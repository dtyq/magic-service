<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Infrastructure\Core\Embeddings\DocumentSplitter;

use Hyperf\Odin\Contract\Model\ModelInterface;

interface DocumentSplitterInterface
{
    /**
     * @return array<string>
     */
    public function split(ModelInterface $model, string $text, array $options = []): array;
}
