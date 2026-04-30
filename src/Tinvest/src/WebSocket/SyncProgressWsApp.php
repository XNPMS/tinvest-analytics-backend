<?php

declare(strict_types=1);

namespace Tinvest\WebSocket;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
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
    /** @var array<string, \SplObjectStorage> jobId => connections */
    private array $subscriptions = [];

    /** @var array<int, string> connId => jobId */
    private array $connectionJobs = [];

    public function __construct(private readonly SyncProgressPublisher $progressPublisher)
    {
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        // Соединение установлено — ждём subscribe-сообщение
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        try {
            $data = json_decode($msg, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $from->send(json_encode(['error' => 'Invalid JSON']));

            return;
        }

        if (($data['action'] ?? null) === 'subscribe' && !empty($data['job_id'])) {
            $jobId  = (string)$data['job_id'];
            $connId = spl_object_id($from);

            if (!isset($this->subscriptions[$jobId])) {
                $this->subscriptions[$jobId] = new \SplObjectStorage();
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
        $this->detachConnection($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
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

            $progress = $this->progressPublisher->getProgress($jobId);

            if (empty($progress['accounts'])) {
                continue;
            }

            $payload = json_encode([
                'job_id'   => $jobId,
                'accounts' => $progress['accounts'],
            ], JSON_THROW_ON_ERROR);

            foreach ($connections as $conn) {
                /** @var ConnectionInterface $conn */
                $conn->send($payload);
            }
        }
    }

    private function pushProgress(string $jobId, ConnectionInterface $conn): void
    {
        $progress = $this->progressPublisher->getProgress($jobId);

        $conn->send(json_encode([
            'job_id'   => $jobId,
            'accounts' => $progress['accounts'],
        ], JSON_THROW_ON_ERROR));
    }

    private function detachConnection(ConnectionInterface $conn): void
    {
        $connId = spl_object_id($conn);
        $jobId  = $this->connectionJobs[$connId] ?? null;

        if ($jobId !== null && isset($this->subscriptions[$jobId])) {
            $this->subscriptions[$jobId]->detach($conn);

            if ($this->subscriptions[$jobId]->count() === 0) {
                unset($this->subscriptions[$jobId]);
            }
        }

        unset($this->connectionJobs[$connId]);
    }
}