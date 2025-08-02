<?php

namespace App\Service;

use App\Gateway\PaymentHttpGateway;
use Hyperf\Redis\Redis;

readonly class GatewayOrchestrator
{
    const string PROVIDER_PAYMENT_NAME = "provider_payment_name";
    const int EXPIRE = 5;
    const int MAX_ERRORS = 1;

    public function __construct(
        private Redis $redis,
        public array $providerList
    ) {
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function handle(): void
    {
        if ($bestProviderRedis = $this->redis->get(self::PROVIDER_PAYMENT_NAME)) {
            $bestProviderDecode = json_decode($bestProviderRedis, true);

            $now = new \DateTimeImmutable();
            if ($now->diff(new \DateTimeImmutable($bestProviderDecode["last_updated_at"]))->s < 5) {
                if (key_exists($bestProviderDecode["provider"], $this->providerList)) {
                    GatewayTable::setProvider($bestProviderDecode["provider"]);
                }
                return;
            }
        }

        $parallel = \Hyperf\Coroutine\parallel(
            array_map(
                fn($g) => fn() => $g->health(),
                $this->providerList
            )
        );

        $lowestResponseTime = PHP_INT_MAX;
        foreach ($parallel as $key => $gateway) {
            if ($gateway->failing) {
                continue;
            }

            if ($gateway->minResponseTime < $lowestResponseTime) {
                $lowestResponseTime = $gateway->minResponseTime;
                $bestProvider = $key;
            }
        }

        $bestProvider = $bestProvider ?? array_key_first($this->providerList);

        $this->redis->setex(
            self::PROVIDER_PAYMENT_NAME,
            self::EXPIRE,
            json_encode([
                'provider' => $bestProvider,
                "last_updated_at" => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.v\Z'),
            ])
        );

        GatewayTable::setProvider($bestProvider);
    }

    public function getGateway(): PaymentHttpGateway
    {
        $provider = GatewayTable::getProvider();

        if (!isset($this->providerList[$provider])) {
            throw new \RuntimeException("Gateway '$provider' não registrado.");
        }

        return $this->providerList[$provider];
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function failed(string $name): void
    {
        $errorInGateway = GatewayTable::setError($name);

        if ($errorInGateway >= self::MAX_ERRORS) {
            $this->handle();
        }
    }
}
