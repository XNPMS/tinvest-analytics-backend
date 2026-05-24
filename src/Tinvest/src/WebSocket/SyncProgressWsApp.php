<?php

declare(strict_types=1);

namespace Tinvest\WebSocket;

use JsonException;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use SplObjectStorage;
use Throwable;
use Tinvest\Enum\SyncStatus;
use Tinvest\Service\SyncProgressPublisher;

/**
 * WebSocket-приложение для трансляции прогресса синхронизации.
 *
 * Клиент подключается и отправляет:
 *   {"action": "subscribe", "job_id": "..."}
 *
 * Сервер периодически (через таймер в команде) читает Memcached
 * и пушит обновления подписчикам:
 *   {"job_id": "...", "accounts": {"123": {"status": 1, "synced_count": 450}}}
 */
class SyncProgressWsApp implements MessageComponentInterface
{
    /** @var array<string, SplObjectStorage> jobId => connections */
    private array $subscriptions = [];
    /** @var array<int, string> connId => jobId */
    private array $connectionJobs = [];
    /** @var array<int, ConnectionInterface> connId => conn - все живые соединения для keepalive */
    private array $allConnections = [];

    public function __construct(
        private readonly SyncProgressPublisher $progressPublisher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->allConnections[spl_object_id($conn)] = $conn;

        $this->logger->info('WS open', ['connId' => spl_object_id($conn)]);
    }

    public function onMessage(ConnectionInterface $from, mixed $msg): void
    {
        try {
            $data = json_decode($msg, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $from->send(json_encode(['error' => 'Invalid JSON'], JSON_THROW_ON_ERROR));
            $this->logger->error(sprintf('Invalid JSON: %s', $msg));

            return;
        }

        $this->logger->info('Start', $data);
        if (($data['action'] ?? null) === 'subscribe' && !empty($data['job_id'])) {
            $jobId  = (string)$data['job_id'];
            $connId = spl_object_id($from);

            if (!isset($this->subscriptions[$jobId])) {
                $this->subscriptions[$jobId] = new SplObjectStorage();
            }

            $this->subscriptions[$jobId]->attach($from);
            $this->connectionJobs[$connId] = $jobId;

            // Сразу отдаём текущий прогресс
            $this->pushProgress($jobId, $from);

            return;
        }

        if (($data['action'] ?? null) === 'unsubscribe' && !empty($data['job_id'])) {
            $this->detachConnection($from);
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $connId = spl_object_id($conn);
        $this->logger->info('WS close', ['connId' => $connId, 'jobId' => $this->connectionJobs[$connId] ?? null]);
        unset($this->allConnections[$connId]);

        $this->detachConnection($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $this->logger->error('WS error', ['connId' => spl_object_id($conn), 'error' => $e->getMessage()]);
        unset($this->allConnections[spl_object_id($conn)]);

        $conn->close();
    }

    /**
     * Вызывается из таймера команды — рассылает обновления всем подписчикам.
     */
    public function broadcastAll(): void
    {
        foreach ($this->subscriptions as $jobId => $connections) {
            if ($connections->count() === 0) {
                unset($this->subscriptions[$jobId]);

                continue;
            }

            try {
                $progress = $this->progressPublisher->getProgress($jobId);
            } catch (Throwable | InvalidArgumentException $e) {
                $this->logger->error('Failed to get progress', ['jobId' => $jobId, 'error' => $e->getMessage()]);

                continue;
            }

            if (empty($progress['accounts'])) {
                $this->logger->debug('No accounts yet for job', [
                    'jobId' => $jobId,
                    'subscribers' => $connections->count(),
                ]);

                continue;
            }

            try {
                $payload = json_encode(['job_id' => $jobId, 'accounts' => $progress['accounts']], JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                $this->logger->error('JSON encode failed', ['jobId' => $jobId, 'error' => $e->getMessage()]);

                continue;
            }

            $this->logger->info('Broadcasting', [
                'jobId' => $jobId,
                'accounts' => array_keys($progress['accounts']),
                'subscribers' => $connections->count(),
            ]);

            foreach ($connections as $conn) {
                /** @var ConnectionInterface $conn */
                try {
                    $conn->send($payload);
                } catch (Throwable $e) {
                    $this->logger->error('Send failed', [
                        'connId' => spl_object_id($conn),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($this->isJobTerminal($progress)) {
                $this->logger->info('Job terminal, unsubscribing all', ['jobId' => $jobId]);
                $this->unsubscribeJob($jobId);
            }
        }
    }

    /**
     * Отправляет ping всем живым соединениям чтобы не рвался idle-таймаут nginx.
     *
     * @throws JsonException
     */
    public function pingAll(): void
    {
        $payload = json_encode(['type' => 'ping'], JSON_THROW_ON_ERROR);
        foreach ($this->allConnections as $connId => $conn) {
            try {
                $conn->send($payload);
            } catch (Throwable $e) {
                $this->logger->error('Ping failed', ['connId' => $connId, 'error' => $e->getMessage()]);
                unset($this->allConnections[$connId]);
            }
        }
    }

    private function pushProgress(string $jobId, ConnectionInterface $conn): void
    {
        try {
            $progress = $this->progressPublisher->getProgress($jobId);
        } catch (Throwable | InvalidArgumentException $e) {
            $this->logger->error('pushProgress failed', ['jobId' => $jobId, 'error' => $e->getMessage()]);

            return;
        }

        $this->logger->info('Accounts', $progress['accounts']);

        try {
            $conn->send(json_encode([
                'job_id' => $jobId,
                'accounts' => $progress['accounts'],
            ], JSON_THROW_ON_ERROR));
        } catch (Throwable $e) {
            $this->logger->error('pushProgress send failed', ['jobId' => $jobId, 'error' => $e->getMessage()]);
        }
    }

    private function isJobTerminal(array $progress): bool
    {
        if (empty($progress['accounts'])) {
            return false;
        }

        foreach ($progress['accounts'] as $account) {
            if ($account['status'] < SyncStatus::COMPLETED->value) {
                return false;
            }
        }

        return true;
    }

    private function unsubscribeJob(string $jobId): void
    {
        $connections = $this->subscriptions[$jobId] ?? null;
        if ($connections === null) {
            return;
        }

        foreach ($connections as $conn) {
            unset($this->connectionJobs[spl_object_id($conn)]);
        }

        unset($this->subscriptions[$jobId]);
    }

    private function detachConnection(ConnectionInterface $conn): void
    {
        $connId = spl_object_id($conn);
        $jobId = $this->connectionJobs[$connId] ?? null;

        if ($jobId !== null && isset($this->subscriptions[$jobId])) {
            $this->subscriptions[$jobId]->detach($conn);

            if ($this->subscriptions[$jobId]->count() === 0) {
                unset($this->subscriptions[$jobId]);
            }
        }

        unset($this->connectionJobs[$connId]);
    }
}
