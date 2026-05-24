<?php

declare(strict_types=1);

namespace Tinvest\UseCase;

use Tinvest\Repository\TinvestOperationRepository;
use Tinvest\Service\CurrencyRateService;

readonly class RecalculatePaymentRubUseCase
{
    public function __construct(
        private TinvestOperationRepository $operationRepository,
        private CurrencyRateService $rateService,
    ) {
    }

    /**
     * Заполняет payment_rub / commission_rub / fx_rate для операций без рублёвой суммы.
     * Запускается после синхронизации и обновления курсов валют.
     *
     * @param int[] $accountIds DB-идентификаторы счетов
     */
    public function execute(array $accountIds): void
    {
        if (!$accountIds) {
            return;
        }

        // RUB-операции пересчитываем напрямую - курс 1:1
        foreach ($accountIds as $accountId) {
            $this->operationRepository->updatePaymentRubForAccount((int)$accountId, 'rub', 1.0);
        }

        // Для иностранных валют берём самый свежий курс из БД
        $allCurrencies = $this->operationRepository->findDistinctCurrenciesByAccountIds($accountIds);

        $foreignCurrencies = array_values(
            array_filter($allCurrencies, static fn(string $c) => $c !== 'rub')
        );

        if (!$foreignCurrencies) {
            return;
        }

        $rates = $this->rateService->getRatesByCurrencies($foreignCurrencies);

        foreach ($accountIds as $accountId) {
            foreach ($rates as $currency => $rate) {
                $this->operationRepository->updatePaymentRubForAccount((int)$accountId, $currency, $rate);
            }
        }
    }
}
