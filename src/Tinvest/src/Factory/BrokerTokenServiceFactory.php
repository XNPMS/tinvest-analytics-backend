<?php

declare(strict_types=1);

namespace Tinvest\Factory;

use Psr\Container\ContainerInterface;
use RuntimeException;
use Tinvest\Repository\BrokerTokenRepository;
use Tinvest\Service\BrokerTokenService;
use User\Service\UserService;

class BrokerTokenServiceFactory
{
    public function __invoke(ContainerInterface $container): BrokerTokenService
    {
        $rawKey = (string)getenv('BROKER_TOKEN_ENCRYPTION_KEY');

        if ($rawKey === '') {
            throw new RuntimeException('BROKER_TOKEN_ENCRYPTION_KEY env variable is not set');
        }

        $key = base64_decode($rawKey, true);

        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException(
                'BROKER_TOKEN_ENCRYPTION_KEY must be a base64-encoded 32-byte key'
            );
        }

        return new BrokerTokenService(
            $container->get(BrokerTokenRepository::class),
            $container->get(UserService::class),
            $key,
        );
    }
}
