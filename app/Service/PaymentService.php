<?php

namespace App\Service;

use GuzzleHttp\Exception\GuzzleException;
use Hyperf\Redis\Redis;
use function Hyperf\Coroutine\parallel;

class PaymentService
{

    public function __construct(
        public readonly Redis $redis,
        public readonly GatewayOrchestrator $orchestrator
    ) {
    }

    /**
     * @throws GuzzleException|\DateMalformedStringException
     */
    public function create(float $amount, string $correlationId, \DateTimeImmutable $requestedAt, int $attempt): void
    {
        $gateway = $this->orchestrator->getGateway();
        try {
            $gateway->create($amount, $correlationId, $requestedAt);

            $this->redis->zAdd("payment:{$gateway->name}", $requestedAt->format('Uv'), json_encode([
                'amount' => $amount,
                'correlation_id' => $correlationId,
                'requested_at' => $requestedAt->format('Uv'),
                'gateway' => $gateway->name,
            ]));
        } catch (\Throwable $exception) {
            $this->orchestrator->failed($gateway->name);

//            var_dump("Erro ao processar pagamento $correlationId na tentativa $attempt, com o seguinte error {$exception->getMessage()}");
            $this->redis->rPush(
                'queue:payment',
                json_encode([
                    'amount' => $amount,
                    'correlation_id' => $correlationId,
                    'requested_at' => $requestedAt->format('Y-m-d\TH:i:s.v\Z'),
                    'attempt' => $attempt++,
                ])
            );
            return;
        }

    }

    public function summary(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return parallel([
            'default' => fn () => $this->getSummary($from, $to, 'default'),
            'fallback' => fn () => $this->getSummary($from, $to, 'fallback'),
        ]);
    }

    private function getSummary(\DateTimeImmutable $fromTs, \DateTimeImmutable $toTs, string $gateway): array
    {
        if ($entries = $this->redis->zRangeByScore("payment:$gateway", $fromTs->format('Uv'), $toTs->format('Uv'))) {
            $totalRequests = count($entries);
            $totalAmount = array_reduce($entries, function ($sum, $entry) {
                $data = json_decode($entry, true);
                return $sum + ($data['amount'] ?? 0);
            }, 0);

            return [
                'totalRequests' => $totalRequests,
                'totalAmount' => $totalAmount,
            ];
        }

        return [
            'totalRequests' => 0,
            'totalAmount' => 0,
        ];
    }
}
