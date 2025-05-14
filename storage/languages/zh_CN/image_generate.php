<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */
return [
    'general_error' => '文生图服务异常',
    'response_format_error' => '文生图响应格式错误',
    'missing_image_data' => '生成图片数据异常',
    'no_valid_image_generated' => '未生成有效图片',
    'input_image_audit_failed' => '您发送的图片不符合相关规定及要求，无法为您生成对应的图片',
    'output_image_audit_failed' => '输出图片后审核未通过',
    'input_text_audit_failed' => '您发送的文本不符合相关规定及要求，无法为您生成对应的图片',
    'output_text_audit_failed' => '输出文本后审核未通过',
    'text_blocked' => '输入文本包含敏感内容，已被拦截',
    'invalid_prompt' => 'Prompt 内容不合法',
    'prompt_check_failed' => 'Prompt 校验失败',
    'polling_failed' => '轮询任务结果失败',
    'task_timeout' => '任务执行超时',
    'invalid_request_type' => '无效的请求类型',
    'missing_job_id' => '缺少任务ID',
    'task_failed' => '任务执行失败',
    'polling_response_format_error' => '轮询响应格式错误',
    'missing_image_url' => '未获取到图片URL',
    'prompt_check_response_error' => 'Prompt 校验响应格式错误',
    'api_request_failed' => '调用图片生成接口失败',
    'image_to_image_missing_source' => '图生图缺少资源: 图片 或者 base64',
    'output_image_audit_failed_with_reason' => '无法生成图片，请尝试更换提示词',
    'task_timeout_with_reason' => '文生图任务未找到或已过期',
    'not_found_error_code' => '未知错误码',
    'unsupported_image_format' => '仅支持 JPG、JPEG、BMP、PNG 格式的图片',
    'invalid_aspect_ratio' => '图生图的尺寸比例差距过大，只能相差3倍',
    'image_url_is_empty' => '图片为空',
];
