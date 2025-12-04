<?php

declare(strict_types=1);

namespace System\Queue\Client;

use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use System\Queue\Config\RabbitMQConfig;

class RabbitMQ
{
    private ?AMQPStreamConnection $connection = null;

    public function __construct(private readonly RabbitMQConfig $config)
    {
    }

    /**
     * @throws Exception
     */
    public function getConnection(): AMQPStreamConnection
    {
        if ($this->connection === null || !$this->connection->isConnected()) {
            $this->connection = new AMQPStreamConnection(
                $this->config->host,
                $this->config->port,
                $this->config->username,
                $this->config->password,
                $this->config->vhost
            );
        }

        return $this->connection;
    }

    /**
     * @throws Exception
     */
    public function reconnect(): void
    {
        try {
            $this->connection?->close();
        } catch (\Throwable) {
            // ignore
        }

        $this->connection = null;
        $this->getConnection();
    }

    /**
     * @throws Exception
     */
    public function close(): void
    {
        if ($this->connection && $this->connection->isConnected()) {
            $this->connection->close();
        }
    }
}
