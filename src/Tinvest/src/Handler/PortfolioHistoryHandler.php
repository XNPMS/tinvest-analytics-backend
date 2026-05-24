<?php

declare(strict_types=1);

namespace Tinvest\Handler;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use System\Exception\Http\BadRequestException;
use System\Handler\AbstractHandler;
use System\Service\UseInputFilterTrait;
use Tinvest\Exception\EntityNotFountException;
use Tinvest\InputFilter\PortfolioHistoryInputFilter;
use Tinvest\Service\CurrencyRateService;
use Tinvest\UseCase\GetPortfolioHistoryUseCase;
use User\Entity\User;

final class PortfolioHistoryHandler extends AbstractHandler
{
    use UseInputFilterTrait;

    public function __construct(
        private readonly GetPortfolioHistoryUseCase $useCase,
        private readonly PortfolioHistoryInputFilter $inputFilter,
    ) {
    }

    /**
     * @throws BadRequestException
     * @throws Exception
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->validateRequest($request->getQueryParams());

        /** @var User $user */
        $user = $request->getAttribute(User::class);
        $brokerAccountId = $this->inputFilter->getValue('account_id');

        try {
            $result = $this->useCase->execute(
                $user->getId(),
                $brokerAccountId !== null && $brokerAccountId !== '' ? (int)$brokerAccountId : null,
                date(CurrencyRateService::DATE_FORMAT),
                $this->inputFilter->getValue('from'),
                (int)$this->inputFilter->getValue('period'),
            );
        } catch (EntityNotFountException $e) {
            throw BadRequestException::create($e->getMessage());
        }

        if ($result === null) {
            return $this->noContent();
        }

        return $this->jsonResponse($result);
    }
}
