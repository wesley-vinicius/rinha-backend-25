<?php

namespace App\Gateway;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

readonly class PaymentHttpGateway
{
    public function __construct(
        public string $name,
        private Client $client,
    ) {
    }

    /**
     * @throws GuzzleException
     */
    public function create(float $amount, string $correlationId, \DateTimeImmutable $requestedAt): bool {
        $response = $this->client->post('/payments', [
            "json" => [
                "amount" => $amount,
                "correlationId" => $correlationId,
                "requestedAt" => $requestedAt->format('Y-m-d\TH:i:s.v\Z'),
            ]
        ]);

        return json_decode($response->getBody()->getContents(), true)["message"] === "payment processed successfully";
    }

    public function health(): Health {
        try {
            $response = $this->client->get('/payments/service-health');
            return Health::fromArray(json_decode($response->getBody()->getContents(), true));
        } catch (\Throwable $exception) {
            var_dump($exception->getMessage());
            return new Health(true, PHP_INT_MAX);
        }
    }
}
