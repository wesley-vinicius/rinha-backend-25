<?php

namespace App\Controller;

use App\Profiler\Profiler;
use App\Service\PaymentService;
use Hyperf\HttpMessage\Exception\BadRequestHttpException;
use Hyperf\Redis\Redis;

readonly class PaymentController
{
    public function __construct(
        private PaymentService $paymentService,
        private Redis $redis,
    ) {
    }

    public function create(\Hyperf\HttpServer\Contract\RequestInterface $request): void
    {
        $amount = $request->input("amount");
        $correlationId = $request->input("correlationId");

        if ($amount <= 0) {
            throw new BadRequestHttpException('Amount must be greater than 0');
        }

        $data = [
            'amount' => $amount,
            'correlation_id' => $correlationId,
            'requested_at' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.v\Z'),
        ];

        Profiler::profileRequest(uniqid('payment_', true), function () use ($data) {
            $this->redis->rPush(
                'queue:payment',
                json_encode($data)
            );
        }, $data);
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function summary(\Hyperf\HttpServer\Contract\RequestInterface $request): array
    {
        return Profiler::profileRequest(
            uniqid('summary_', true),
            function () use ($request) {
                return $this->paymentService->summary(
                    new \DateTimeImmutable($request->input('from', '')),
                    new \DateTimeImmutable($request->input('to', ''))
                );
            }
        );
    }

    public function purge(): void
    {
        $this->redis->flushAll();
    }
}