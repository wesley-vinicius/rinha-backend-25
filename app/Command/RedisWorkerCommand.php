<?php

namespace App\Command;

use App\Service\GatewayOrchestrator;
use App\Service\PaymentService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Redis\Redis;
use Hyperf\Utils\Coroutine as UtilsCoroutine;


#[Command]
class RedisWorkerCommand extends HyperfCommand
{
    public function __construct(
        private PaymentService $paymentService,
        private GatewayOrchestrator $gatewayOrchestrator,
        private Redis $redis
    ) {
        parent::__construct('payment:queue');
    }


    public function configure(): void
    {
        $this->setDescription('Executa workers Redis em corrotinas');
    }

    public function handle(): void
    {
        $this->startCb();
        for ($i = 0; $i < 20; $i++) {
            go(function () use ($i) {
                $this->info("Worker $i iniciado");
                while (true) {
                    $data = $this->redis->blPop(['queue:payment'], 4);

                    if (!empty($data)) {
                        try {
                            $payload = json_decode($data[1], true);

                            $this->paymentService->create(
                                amount: $payload['amount'],
                                correlationId: $payload['correlation_id'],
                                requestedAt: new \DateTimeImmutable($payload['requested_at']),
                                attempt: $payload['attempt'] ?? 1
                            );
                        } catch (\Throwable $exception) {
                            var_dump($exception->getMessage());
                        }
                    }
                }
            });
        }
    }

    public function startCb(): void
    {
        go(function () {
            while (true) {
                try {
                    $this->gatewayOrchestrator->handle();
                } catch (\Throwable $exception) {
                    var_dump($exception->getMessage());
                }

                sleep(5);
            }
        });
    }
}
