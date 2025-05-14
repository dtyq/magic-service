<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Domain\Flow\Repository\Persistence;

use App\Infrastructure\Core\AbstractRepository;

abstract class MagicFlowAbstractRepository extends AbstractRepository
{
    protected array $attributeMaps = [
        'creator' => 'created_uid',
        'modifier' => 'updated_uid',
    ];
}
