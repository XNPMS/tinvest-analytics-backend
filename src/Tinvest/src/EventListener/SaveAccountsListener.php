<?php

declare(strict_types=1);

namespace Tinvest\EventListener;

use Tinvest\Event\AccountsFetchedEvent;
use Tinvest\Service\TinvestAccountService;

readonly class SaveAccountsListener
{
    public function __construct(private TinvestAccountService $accountService)
    {
    }

    public function onAccountsFetched(AccountsFetchedEvent $event): void
    {
        $params = $event->getParams();
        $accounts = $params['accounts'] ?? [];
        $userId = $params['user_id'] ?? null;

        if (!$userId || empty($accounts)) {
            return;
        }

        $this->accountService->createTinvestAccounts($userId, $accounts);
    }
}
