<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Infrastructure\ExternalAPI\ImageGenerateAPI\Model\Volcengine;

use App\Domain\ModelAdmin\Entity\ValueObject\ServiceProviderConfig;
use App\ErrorCode\ImageGenerateErrorCode;
use App\Infrastructure\Core\Exception\ExceptionBuilder;
use App\Infrastructure\ExternalAPI\ImageGenerateAPI\ImageGenerate;
use App\Infrastructure\ExternalAPI\ImageGenerateAPI\ImageGenerateType;
use App\Infrastructure\ExternalAPI\ImageGenerateAPI\Request\ImageGenerateRequest;
use App\Infrastructure\ExternalAPI\ImageGenerateAPI\Request\VolcengineModelRequest;
use App\Infrastructure\ExternalAPI\ImageGenerateAPI\Response\ImageGenerateResponse;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

class VolcengineImageGenerateV3Model implements ImageGenerate
{
    // 最大轮询重试次数
    private const MAX_RETRY_COUNT = 30;

    // 轮询重试间隔（秒）
    private const RETRY_INTERVAL = 2;

    #[Inject]
    protected LoggerInterface $logger;

    private VolcengineAPI $api;

    public function __construct(ServiceProviderConfig $serviceProviderConfig)
    {
        $this->api = new VolcengineAPI($serviceProviderConfig->getAk(), $serviceProviderConfig->getSk());
    }

    public function generateImage(ImageGenerateRequest $imageGenerateRequest): ImageGenerateResponse
    {
        if (! $imageGenerateRequest instanceof VolcengineModelRequest) {
            $this->logger->error('火山文生图：无效的请求类型', ['class' => get_class($imageGenerateRequest)]);
            ExceptionBuilder::throw(ImageGenerateErrorCode::GENERAL_ERROR);
        }

        // 判断是图生图还是文生图
        $count = $imageGenerateRequest->getGenerateNum();

        $this->logger->info('火山文生图：开始生图', [
            'prompt' => $imageGenerateRequest->getPrompt(),
            'negativePrompt' => $imageGenerateRequest->getNegativePrompt(),
            'width' => $imageGenerateRequest->getWidth(),
            'height' => $imageGenerateRequest->getHeight(),
            'req_key' => $imageGenerateRequest->getModel(),
        ]);

        // 使用同步方式处理
        $results = [];
        for ($i = 0; $i < $count; ++$i) {
            try {
                // 提交任务（带重试）
                $taskId = $this->submitAsyncTask($imageGenerateRequest);
                // 轮询结果（带重试）
                $result = $this->pollTaskResult($taskId, $imageGenerateRequest->getModel());

                $results[] = [
                    'success' => true,
                    'data' => $result['data'],
                    'index' => $i,
                ];
            } catch (Exception $e) {
                $this->logger->error('火山文生图：失败', [
                    'error' => $e->getMessage(),
                    'index' => $i,
                ]);
                $results[] = [
                    'success' => false,
                    'error_code' => $e->getCode(),
                    'error_msg' => $e->getMessage(),
                    'index' => $i,
                ];
            }
        }

        $imageUrls = [];
        $errors = [];

        // 处理结果
        foreach ($results as $result) {
            if ($result['success']) {
                $data = $result['data'];
                if (! empty($data['binary_data_base64'])) {
                    $imageUrls[$result['index']] = $data['binary_data_base64'][0];
                } elseif (! empty($data['image_urls'])) {
                    $imageUrls[$result['index']] = $data['image_urls'][0];
                }
            } else {
                $errors[] = [
                    'code' => $result['error_code'] ?? ImageGenerateErrorCode::GENERAL_ERROR->value,
                    'message' => $result['error_msg'] ?? '',
                ];
            }
        }

        if (empty($imageUrls)) {
            // 优先使用具体的错误码，如果都是通用错误则使用 NO_VALID_IMAGE
            $finalErrorCode = ImageGenerateErrorCode::NO_VALID_IMAGE;
            $finalErrorMsg = '';

            foreach ($errors as $error) {
                if ($error['code'] !== ImageGenerateErrorCode::GENERAL_ERROR->value) {
                    $finalErrorCode = ImageGenerateErrorCode::from($error['code']);
                    $finalErrorMsg = $error['message'];
                    break;
                }
            }

            // 如果没有找到具体错误消息，使用第一个错误消息
            if (empty($finalErrorMsg) && ! empty($errors[0]['message'])) {
                $finalErrorMsg = $errors[0]['message'];
            }

            $this->logger->error('火山文生图：所有图片生成均失败', ['errors' => $errors]);
            ExceptionBuilder::throw($finalErrorCode, $finalErrorMsg);
        }

        // 按索引排序结果
        ksort($imageUrls);
        $imageUrls = array_values($imageUrls);

        $this->logger->info('火山文生图：生成结束', [
            '生成图片' => $imageUrls,
            '图片数量' => $count,
        ]);

        return new ImageGenerateResponse(ImageGenerateType::URL, $imageUrls);
    }

    public function setAK(string $ak)
    {
        $this->api->setAk($ak);
    }

    public function setSK(string $sk)
    {
        $this->api->setSk($sk);
    }

    public function setApiKey(string $apiKey)
    {
        // TODO: Implement setApiKey() method.
    }

    private function submitAsyncTask(VolcengineModelRequest $request): string
    {
        $prompt = $request->getPrompt();
        $width = (int) $request->getWidth();
        $height = (int) $request->getHeight();

        try {
            $body = [
                'return_url' => true,
                'prompt' => $prompt,
                'width' => $width,
                'height' => $height,
                'req_key' => $request->getModel(),
            ];

            $response = $this->api->submitTask($body);

            if (! isset($response['code'])) {
                $this->logger->warning('火山文生图：响应格式错误', ['response' => $response]);
                ExceptionBuilder::throw(ImageGenerateErrorCode::RESPONSE_FORMAT_ERROR);
            }

            if ($response['code'] !== 10000) {
                $errorMsg = $response['message'] ?? '';
                $errorCode = match ($response['code']) {
                    50411 => ImageGenerateErrorCode::INPUT_IMAGE_AUDIT_FAILED,
                    50511 => ImageGenerateErrorCode::OUTPUT_IMAGE_AUDIT_FAILED_WITH_REASON,
                    50412, 50413 => ImageGenerateErrorCode::INPUT_TEXT_AUDIT_FAILED,
                    default => ImageGenerateErrorCode::GENERAL_ERROR,
                };

                $this->logger->warning('火山文生图：任务提交失败', [
                    'code' => $response['code'],
                    'message' => $response['message'] ?? '',
                ]);

                ExceptionBuilder::throw($errorCode, $errorMsg);
            }

            if (! isset($response['data']['task_id'])) {
                $this->logger->warning('火山文生图：响应中缺少任务ID', ['response' => $response]);
                ExceptionBuilder::throw(ImageGenerateErrorCode::RESPONSE_FORMAT_ERROR);
            }

            $taskId = $response['data']['task_id'];

            $this->logger->info('火山文生图：提交任务成功', [
                'taskId' => $taskId,
            ]);

            return $taskId;
        } catch (Exception $e) {
            $this->logger->error('火山文生图：任务提交异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            ExceptionBuilder::throw(ImageGenerateErrorCode::GENERAL_ERROR);
        }
    }

    private function pollTaskResult(string $taskId, string $model): array
    {
        $reqKey = $model;
        $retryCount = 0;

        while ($retryCount < self::MAX_RETRY_COUNT) {
            try {
                $params = [
                    'task_id' => $taskId,
                    'req_key' => $reqKey,
                    'req_json' => '{"return_url":true}',
                ];

                $response = $this->api->getTaskResult($params);

                if (! isset($response['code'])) {
                    $this->logger->warning('火山文生图：查询任务响应格式错误', ['response' => $response]);
                    ExceptionBuilder::throw(ImageGenerateErrorCode::RESPONSE_FORMAT_ERROR);
                }

                if ($response['code'] !== 10000) {
                    $errorMsg = $response['message'] ?? '';
                    $errorCode = match ($response['code']) {
                        50411 => ImageGenerateErrorCode::INPUT_IMAGE_AUDIT_FAILED,
                        50511 => ImageGenerateErrorCode::OUTPUT_IMAGE_AUDIT_FAILED_WITH_REASON,
                        50412, 50413 => ImageGenerateErrorCode::INPUT_TEXT_AUDIT_FAILED,
                        50512 => ImageGenerateErrorCode::OUTPUT_TEXT_AUDIT_FAILED,
                        default => ImageGenerateErrorCode::GENERAL_ERROR,
                    };

                    $this->logger->warning('火山文生图：查询任务失败', [
                        'code' => $response['code'],
                        'message' => $response['message'] ?? '',
                    ]);

                    ExceptionBuilder::throw($errorCode, $errorMsg);
                }

                if (! isset($response['data']) || ! isset($response['data']['status'])) {
                    $this->logger->warning('火山文生图：响应格式错误', ['response' => $response]);
                    ExceptionBuilder::throw(ImageGenerateErrorCode::RESPONSE_FORMAT_ERROR);
                }

                $data = $response['data'];
                $status = $data['status'];

                $this->logger->info('火山文生图：任务状态', [
                    'taskId' => $taskId,
                    'status' => $status,
                ]);

                switch ($status) {
                    case 'done':
                        if (! empty($data['binary_data_base64']) || ! empty($data['image_urls'])) {
                            return $response;
                        }
                        $this->logger->error('火山文生图：任务完成但缺少图片数据', ['response' => $response]);
                        ExceptionBuilder::throw(ImageGenerateErrorCode::MISSING_IMAGE_DATA);
                        // no break
                    case 'in_queue':
                    case 'generating':
                        break;
                    case 'not_found':
                        $this->logger->error('火山文生图：任务未找到或已过期', ['taskId' => $taskId]);
                        ExceptionBuilder::throw(ImageGenerateErrorCode::TASK_TIMEOUT_WITH_REASON);
                        // no break
                    default:
                        $this->logger->error('火山文生图：未知的任务状态', ['status' => $status, 'response' => $response]);
                        ExceptionBuilder::throw(ImageGenerateErrorCode::TASK_TIMEOUT_WITH_REASON);
                }

                ++$retryCount;
                sleep(self::RETRY_INTERVAL);
            } catch (Exception $e) {
                $this->logger->error('火山文生图：查询任务异常', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'taskId' => $taskId,
                ]);

                ExceptionBuilder::throw(ImageGenerateErrorCode::GENERAL_ERROR);
            }
        }

        $this->logger->error('火山文生图：任务查询超时', ['taskId' => $taskId]);
        ExceptionBuilder::throw(ImageGenerateErrorCode::TASK_TIMEOUT);
    }
}
