<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Application\MCP\Service;

use App\Application\Kernel\AbstractKernelAppService;
use App\Application\Permission\Service\OperationPermissionAppService;
use App\Domain\MCP\Entity\ValueObject\MCPDataIsolation;
use App\Domain\MCP\Service\MCPServerDomainService;
use App\Domain\Permission\Entity\ValueObject\OperationPermission\Operation;
use App\Domain\Permission\Entity\ValueObject\OperationPermission\ResourceType;

abstract class AbstractMCPAppService extends AbstractKernelAppService
{
    public function __construct(
        protected readonly MCPServerDomainService $mcpServerDomainService,
        protected readonly OperationPermissionAppService $operationPermissionAppService,
    ) {
    }

    protected function getMCPServerOperation(MCPDataIsolation $dataIsolation, int|string $code): Operation
    {
        if (empty($code)) {
            return Operation::None;
        }
        $permissionDataIsolation = $this->createPermissionDataIsolation($dataIsolation);
        return $this->operationPermissionAppService->getOperationByResourceAndUser(
            $permissionDataIsolation,
            ResourceType::MCPServer,
            (string) $code,
            $permissionDataIsolation->getCurrentUserId()
        );
    }
}
