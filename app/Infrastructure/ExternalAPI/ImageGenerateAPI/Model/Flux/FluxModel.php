<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Infrastructure\ExternalAPI\ImageGenerateAPI\Model\Flux;

use App\Domain\ModelAdmin\Entity\ValueObject\ServiceProviderConfig;
use App\ErrorCode\ImageGenerateErrorCode;
use App\Infrastructure\Core\Exception\ExceptionBuilder;
use App\Infrastructure\ExternalAPI\ImageGenerateAPI\ImageGenerate;
use App\Infrastructure\ExternalAPI\ImageGenerateAPI\ImageGenerateModelType;
use App\Infrastructure\ExternalAPI\ImageGenerateAPI\ImageGenerateType;
use App\Infrastructure\ExternalAPI\ImageGenerateAPI\Model\AbstractDingTalkAlert;
use App\Infrastructure\ExternalAPI\ImageGenerateAPI\Request\FluxModelRequest;
use App\Infrastructure\ExternalAPI\ImageGenerateAPI\Request\ImageGenerateRequest;
use App\Infrastructure\ExternalAPI\ImageGenerateAPI\Response\ImageGenerateResponse;
use App\Infrastructure\Util\Context\CoContext;
use Exception;
use Hyperf\Coroutine\Parallel;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Engine\Coroutine;
use Hyperf\RateLimit\Annotation\RateLimit;
use Hyperf\Retry\Annotation\Retry;
use Psr\Log\LoggerInterface;

class FluxModel extends AbstractDingTalkAlert implements ImageGenerate
{
    protected const MAX_RETRIES = 20;

    protected const RETRY_INTERVAL = 10;

    #[Inject]
    protected LoggerInterface $logger;

    protected FluxAPI $api;

    public function __construct(ServiceProviderConfig $serviceProviderConfig)
    {
        parent::__construct();
        $this->api = new FluxAPI($serviceProviderConfig->getApiKey());
        $this->balanceThreshold = 100;
    }

    public function generateImage(ImageGenerateRequest $imageGenerateRequest): ImageGenerateResponse
    {
        if (! $imageGenerateRequest instanceof FluxModelRequest) {
            $this->logger->error('Flux文生图：无效的请求类型', ['class' => get_class($imageGenerateRequest)]);
            ExceptionBuilder::throw(ImageGenerateErrorCode::GENERAL_ERROR);
        }

        $count = $imageGenerateRequest->getGenerateNum();
        $imageUrls = [];
        $errors = [];

        // 使用 Parallel 并行处理
        $parallel = new Parallel();
        $fromCoroutineId = Coroutine::id();
        for ($i = 0; $i < $count; ++$i) {
            $parallel->add(function () use ($imageGenerateRequest, $i, $fromCoroutineId) {
                CoContext::copy($fromCoroutineId);
                try {
                    $jobId = $this->requestImageGeneration($imageGenerateRequest);
                    $result = $this->pollTaskResult($jobId);
                    return [
                        'success' => true,
                        'data' => $result,
                        'index' => $i,
                    ];
                } catch (Exception $e) {
                    $this->logger->error('Flux文生图：图片生成失败', [
                        'error' => $e->getMessage(),
                        'index' => $i,
                    ]);
                    return [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'index' => $i,
                    ];
                }
            });
        }

        // 获取所有并行任务的结果
        $results = $parallel->wait();

        // 处理结果
        foreach ($results as $result) {
            if ($result['success']) {
                foreach ($result['data']->getData() as $url) {
                    $imageUrls[$result['index']] = $url;
                }
            } else {
                $errors[] = $result['error'];
            }
        }

        // 检查是否至少有一张图片生成成功
        if (empty($imageUrls)) {
            $errorMessage = implode('; ', $errors);
            $this->logger->error('Flux文生图：所有图片生成均失败', ['errors' => $errors]);
            ExceptionBuilder::throw(ImageGenerateErrorCode::NO_VALID_IMAGE, $errorMessage);
        }

        // 按索引排序结果
        ksort($imageUrls);
        $imageUrls = array_values($imageUrls);

        // 异步检查余额
        $this->monitorBalance();

        $this->logger->info('Flux文生图：生成结束', [
            'totalImages' => count($imageUrls),
            'requestedImages' => $count,
        ]);

        return new ImageGenerateResponse(ImageGenerateType::URL, $imageUrls);
    }

    public function setAK(string $ak)
    {
        // TODO: Implement setAK() method.
    }

    public function setSK(string $sk)
    {
        // TODO: Implement setSK() method.
    }

    public function setApiKey(string $apiKey)
    {
        $this->api->setApiKey($apiKey);
    }

    /**
     * 请求生成图片并返回任务ID.
     */
    #[RateLimit(create: 20, consume: 1, capacity: 0, key: ImageGenerate::IMAGE_GENERATE_KEY_PREFIX . ImageGenerate::IMAGE_GENERATE_SUBMIT_KEY_PREFIX . ImageGenerateModelType::Flux->value, waitTimeout: 60)]
    #[Retry(
        maxAttempts: self::GENERATE_RETRY_COUNT,
        base: self::GENERATE_RETRY_TIME
    )]
    protected function requestImageGeneration(FluxModelRequest $imageGenerateRequest): string
    {
        $prompt = $imageGenerateRequest->getPrompt();
        $size = $imageGenerateRequest->getWidth() . 'x' . $imageGenerateRequest->getHeight();
        $mode = $imageGenerateRequest->getModel();
        // 记录请求开始
        $this->logger->info('Flux文生图：开始生图', [
            'prompt' => $prompt,
            'size' => $size,
            'mode' => $mode,
        ]);

        try {
            $result = $this->api->submitTask($prompt, $size, $mode);

            if ($result['status'] !== 'SUCCESS') {
                $this->logger->warning('Flux文生图：生成请求失败', ['message' => $result['message'] ?? '未知错误']);
                ExceptionBuilder::throw(ImageGenerateErrorCode::GENERAL_ERROR, $result['message']);
            }

            if (empty($result['data']['jobId'])) {
                $this->logger->error('Flux文生图：缺少任务ID', ['response' => $result]);
                ExceptionBuilder::throw(ImageGenerateErrorCode::MISSING_IMAGE_DATA);
            }
            $taskId = $result['data']['jobId'];
            $this->logger->info('Flux文生图：提交任务成功', [
                'taskId' => $taskId,
            ]);
            return $taskId;
        } catch (Exception $e) {
            $this->logger->warning('Flux文生图：调用图片生成接口失败', ['error' => $e->getMessage()]);
            ExceptionBuilder::throw(ImageGenerateErrorCode::GENERAL_ERROR);
        }
    }

    /**
     * 轮询任务结果.
     */
    #[RateLimit(create: 40, consume: 1, capacity: 40, key: ImageGenerate::IMAGE_GENERATE_KEY_PREFIX . self::IMAGE_GENERATE_POLL_KEY_PREFIX . ImageGenerateModelType::Flux->value, waitTimeout: 60)]
    #[Retry(
        maxAttempts: self::GENERATE_RETRY_COUNT,
        base: self::GENERATE_RETRY_TIME
    )]
    protected function pollTaskResult(string $jobId): ImageGenerateResponse
    {
        $retryCount = 0;

        while ($retryCount < self::MAX_RETRIES) {
            try {
                $result = $this->api->getTaskResult($jobId);

                if ($result['status'] === 'SUCCESS') {
                    if (! empty($result['data']['imageUrl'])) {
                        return new ImageGenerateResponse(ImageGenerateType::URL, [$result['data']['imageUrl']]);
                    }

                    $this->logger->error('Flux文生图：未获取到图片URL', ['response' => $result]);
                    ExceptionBuilder::throw(ImageGenerateErrorCode::MISSING_IMAGE_DATA);
                }

                if ($result['status'] === 'FAILED') {
                    $this->logger->error('Flux文生图：任务执行失败', ['message' => $result['message'] ?? '未知错误']);
                    ExceptionBuilder::throw(ImageGenerateErrorCode::GENERAL_ERROR, $result['message']);
                }

                ++$retryCount;
                sleep(self::RETRY_INTERVAL);
            } catch (Exception $e) {
                $this->logger->warning('Flux文生图：轮询任务结果失败', ['error' => $e->getMessage(), 'jobId' => $jobId]);
                ExceptionBuilder::throw(ImageGenerateErrorCode::POLLING_FAILED);
            }
        }

        $this->logger->error('Flux文生图：任务执行超时', ['jobId' => $jobId]);
        ExceptionBuilder::throw(ImageGenerateErrorCode::TASK_TIMEOUT);
    }

    /**
     * 检查账户余额.
     * @return float 余额
     * @throws Exception
     */
    protected function checkBalance(): float
    {
        try {
            $result = $this->api->getAccountInfo();

            if ($result['status'] !== 'SUCCESS') {
                throw new Exception('检查余额失败: ' . ($result['message'] ?? '未知错误'));
            }

            return (float) $result['data']['balance'];
        } catch (Exception $e) {
            throw new Exception('检查余额失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取告警消息前缀
     */
    protected function getAlertPrefix(): string
    {
        return 'TT API';
    }
}
