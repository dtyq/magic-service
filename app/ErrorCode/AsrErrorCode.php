<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\ErrorCode;

use App\Infrastructure\Core\Exception\Annotation\ErrorMessage;

enum AsrErrorCode: int
{
    #[ErrorMessage(message: 'common.error')]
    case Error = 43000;

    #[ErrorMessage(message: 'common.request_timeout')]
    case RequestTimeout = 43122;

    #[ErrorMessage(message: 'asr.config_error.invalid_config')]
    case InvalidConfig = 43006;

    #[ErrorMessage(message: 'asr.driver_error.driver_not_found')]
    case DriverNotFound = 43008;

    #[ErrorMessage(message: 'asr.invalid_audio')]
    case InvalidAudioFormat = 43012;

    #[ErrorMessage(message: 'asr.recognize_error')]
    case RecognitionError = 43022;

    #[ErrorMessage(message: 'asr.websocket_connection_failed')]
    case WebSocketConnectionFailed = 43100;

    #[ErrorMessage(message: 'asr.file_not_found')]
    case FileNotFound = 43101;

    #[ErrorMessage(message: 'asr.file_open_failed')]
    case FileOpenFailed = 43102;

    #[ErrorMessage(message: 'asr.file_read_failed')]
    case FileReadFailed = 43103;
}
