<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */
use App\ErrorCode\AgentErrorCode;
use App\ErrorCode\AsrErrorCode;
use App\ErrorCode\AuthenticationErrorCode;
use App\ErrorCode\ChatErrorCode;
use App\ErrorCode\FlowErrorCode;
use App\ErrorCode\GenericErrorCode;
use App\ErrorCode\HttpErrorCode;
use App\ErrorCode\ImageGenerateErrorCode;
use App\ErrorCode\MagicAccountErrorCode;
use App\ErrorCode\MagicApiErrorCode;
use App\ErrorCode\MCPErrorCode;
use App\ErrorCode\PermissionErrorCode;
use App\ErrorCode\ServiceProviderErrorCode;
use App\ErrorCode\ShareErrorCode;
use App\ErrorCode\SuperAgentErrorCode;
use App\ErrorCode\TokenErrorCode;
use App\ErrorCode\UserErrorCode;
use App\ErrorCode\UserTaskErrorCode;
use App\Infrastructure\Core\Exception\BusinessException;

return [
    'exception_class' => BusinessException::class,
    'error_code_mapper' => [
        HttpErrorCode::class => [100, 600],
        UserErrorCode::class => [2150, 2999],
        ChatErrorCode::class => [3000, 3999],
        MagicApiErrorCode::class => [4000, 4100],
        MagicAccountErrorCode::class => [4100, 4300],
        GenericErrorCode::class => [5000, 9000],
        TokenErrorCode::class => [9000, 10000],
        FlowErrorCode::class => [31000, 31999],
        AgentErrorCode::class => [32000, 32999],
        AuthenticationErrorCode::class => [33000, 40999],
        PermissionErrorCode::class => [42000, 42999],
        ImageGenerateErrorCode::class => [44000, 44999],
        AsrErrorCode::class => [43000, 43999],
        UserTaskErrorCode::class => [8000, 8999],
        ServiceProviderErrorCode::class => [44000, 44999],
        SuperAgentErrorCode::class => [51000, 51200],
        ShareErrorCode::class => [51300, 51400],
        MCPErrorCode::class => [51500, 51599],
    ],
];
