<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

use App\Gateway\PaymentHttpGateway;
use App\Service\GatewayOrchestrator;
use GuzzleHttp\HandlerStack;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Guzzle\CoroutineHandler;
use Hyperf\Redis\RedisFactory;

$configGatewayDefault = [
    'base_uri' => 'http://payment-processor-default:8080',
    'timeout' => 10.0,
    'handler' => HandlerStack::create(new CoroutineHandler()),
];

$configGatewayFallback = $configGatewayDefault;
$configGatewayFallback["base_uri"] = 'http://payment-processor-fallback:8080';

return [
    GatewayOrchestrator::class => function (\Psr\Container\ContainerInterface $container) use ($configGatewayDefault, $configGatewayFallback) {
        $factory = $container->get(ClientFactory::class);

        return new GatewayOrchestrator(
            redis: $container->get(RedisFactory::class)->get('default'),
            providerList: [
                'default' =>  new PaymentHttpGateway("default", $factory->create($configGatewayDefault)),
                'fallback' => new PaymentHttpGateway("fallback", $factory->create($configGatewayFallback)),
            ]
        );
    }
];
