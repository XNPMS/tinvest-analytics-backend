<?php

declare(strict_types=1);

namespace Tinvest\Service;

use JsonException;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Tinvest\Enum\SyncStatus;

class SyncProgressPublisher
{
    private const KEY_PREFIX = 'sync.%s';
    private const TTL = 3600;

    public function __construct(private readonly CacheInterface $cache)
    {
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function publish(
        string $jobId,
        int $accountId,
        int $syncedCount,
        SyncStatus $status,
        int $progress = 0,
        int $totalCount = 0,
        ?string $error = null,
        ?string $step = null,
    ): void {
        $data = $this->read($jobId);

        $data['accounts'][$accountId] = [
            'status' => $status->value,
            'synced_count' => $syncedCount,
            'total_count' => $totalCount,
            'progress' => $progress,
            'step' => $step,
            'error' => $error,
            'updated_at' => time(),
        ];

        $this->cache->set($this->getKey($jobId), json_encode($data, JSON_THROW_ON_ERROR), self::TTL);
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function publishStep(string $jobId, int $accountId, string $step, ?int $progress = null): void
    {
        $data = $this->read($jobId);

        if (!isset($data['accounts'][$accountId])) {
            return;
        }

        $data['accounts'][$accountId]['step'] = $step;
        $data['accounts'][$accountId]['updated_at'] = time();

        if ($progress !== null) {
            $data['accounts'][$accountId]['progress'] = $progress;
        }

        $this->cache->set(self::KEY_PREFIX . $jobId, json_encode($data, JSON_THROW_ON_ERROR), self::TTL);
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function getProgress(string $jobId): array
    {
        return $this->read($jobId);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function delete(string $jobId): void
    {
        $this->cache->delete($this->getKey($jobId));
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private function read(string $jobId): array
    {
        $data = $this->cache->get($this->getKey($jobId));

        if ($data === null) {
            return ['accounts' => []];
        }

        return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
    }

    private function getKey(string $jobId): string
    {
        return sprintf(self::KEY_PREFIX, $jobId);
    }
}
