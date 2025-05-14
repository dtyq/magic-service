<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Domain\Flow\Repository\Persistence\Model;

use App\Infrastructure\Core\AbstractModel;
use DateTime;
use Hyperf\Database\Model\SoftDeletes;
use Hyperf\Snowflake\Concern\Snowflake;

/**
 * @property int $id
 * @property string $flow_code
 * @property string $code
 * @property string $name
 * @property string $description
 * @property array $case_config
 * @property string $organization_code
 * @property string $created_uid
 * @property DateTime $created_at
 * @property string $updated_uid
 * @property DateTime $updated_at
 * @property DateTime $deleted_at
 */
class MagicFlowTriggerTestcaseModel extends AbstractModel
{
    use Snowflake;
    use SoftDeletes;

    protected ?string $table = 'magic_flow_trigger_testcases';

    protected array $fillable = [
        'id', 'flow_code', 'code', 'name', 'description', 'case_config',
        'organization_code', 'created_uid', 'created_at', 'updated_uid', 'updated_at', 'deleted_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'code' => 'string',
        'name' => 'string',
        'description' => 'string',
        'case_config' => 'json',
        'organization_code' => 'string',
        'created_uid' => 'string',
        'created_at' => 'datetime',
        'updated_uid' => 'string',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
