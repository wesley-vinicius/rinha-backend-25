<?php

namespace App\Service;

use App\Gateway\PaymentHttpGateway;

readonly class GatewayOrchestrator
{
    const int MAX_ERRORS = 1;

    public function __construct(
        public array $providerList
    ) {
    }

    public function handle(): void
    {
        $parallel = \Hyperf\Coroutine\parallel(
            array_map(
                fn($g) => fn() => $g->health(),
                $this->providerList
            )
        );

        foreach ($parallel as $key => $gateway) {
            if (!$gateway->failing) {
                $bestProvider = $key;
                break;
            }
        }

        $bestProvider = $bestProvider ?? array_key_first($this->providerList);
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

    public function failed(string $name): void
    {
        $errorInGateway = GatewayTable::setError($name);

        if ($errorInGateway >= self::MAX_ERRORS) {
            $this->handle();
        }
    }
}
