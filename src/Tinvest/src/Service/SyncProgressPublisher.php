<?php

declare(strict_types=1);

namespace Tinvest\Service;

use Psr\SimpleCache\CacheInterface;
use Tinvest\Enum\SyncStatus;

class SyncProgressPublisher
{
    private const KEY_PREFIX = 'sync:';
    private const TTL = 3600;

    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function publish(
        string $jobId,
        int $accountId,
        int $syncedCount,
        SyncStatus $status,
        ?string $error = null,
    ): void {
        $key  = self::KEY_PREFIX . $jobId;
        $data = $this->read($jobId);

        $data['accounts'][$accountId] = [
            'status'       => $status->value,
            'synced_count' => $syncedCount,
            'error'        => $error,
            'updated_at'   => time(),
        ];

        $this->cache->set($key, json_encode($data, JSON_THROW_ON_ERROR), self::TTL);
    }

    /**
     * @return array{accounts: array<int, array{status: int, synced_count: int, error: string|null, updated_at: int}>}
     */
    public function getProgress(string $jobId): array
    {
        return $this->read($jobId);
    }

    public function delete(string $jobId): void
    {
        $this->cache->delete(self::KEY_PREFIX . $jobId);
    }

    private function read(string $jobId): array
    {
        $raw = $this->cache->get(self::KEY_PREFIX . $jobId);

        if ($raw === null) {
            return ['accounts' => []];
        }

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}