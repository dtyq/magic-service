<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\ErrorCode;

use App\Infrastructure\Core\Exception\Annotation\ErrorMessage;

enum MCPErrorCode: int
{
    #[ErrorMessage(message: 'mcp.validate_failed')]
    case ValidateFailed = 51500; // 验证失败

    #[ErrorMessage(message: 'mcp.not_found')]
    case NotFound = 51501; // 数据不存在

    // MCP服务相关错误码
    #[ErrorMessage(message: 'mcp.service.already_exists')]
    case ServiceAlreadyExists = 51510; // MCP服务已存在

    #[ErrorMessage(message: 'mcp.service.not_enabled')]
    case ServiceNotEnabled = 51511; // MCP服务未启用

    // 工具关联相关错误码
    #[ErrorMessage(message: 'mcp.rel.not_found')]
    case RelNotFound = 51520; // 关联资源不存在

    #[ErrorMessage(message: 'mcp.rel_version.not_found')]
    case RelVersionNotFound = 51521; // 关联资源版本不存在

    #[ErrorMessage(message: 'mcp.rel.not_enabled')]
    case RelNotEnabled = 51522; // 关联资源未启用

    #[ErrorMessage(message: 'mcp.tool.execute_failed')]
    case ToolExecuteFailed = 51523; // 工具执行失败
}
