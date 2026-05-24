<?php

declare(strict_types=1);

namespace Tinvest\InputFilter;

use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\Between;
use Laminas\Validator\Date;
use Laminas\Validator\Digits;
use Laminas\Validator\GreaterThan;

final class PortfolioHistoryInputFilter extends InputFilter
{
    public function init(): void
    {
        $accountId = new Input('account_id');
        $accountId->setRequired(false);
        $accountId->setAllowEmpty(true);
        $accountId->getValidatorChain()
            ->attach(new Digits())
            ->attach(new GreaterThan(['min' => 0, 'inclusive' => false]));

        $period = new Input('period');
        $period->setRequired(false);
        $period->setFallbackValue(30);
        $period->getValidatorChain()
            ->attach(new Digits())
            ->attach(new Between(['min' => 0, 'max' => 3650, 'inclusive' => true]));

        $from = new Input('from');
        $from->setRequired(false);
        $from->setAllowEmpty(true);
        $from->getValidatorChain()
            ->attach(new Date(['format' => 'Y-m-d']));

        $this->add($accountId);
        $this->add($period);
        $this->add($from);
    }
}
