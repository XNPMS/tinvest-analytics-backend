<?php

declare(strict_types=1);

namespace System\Queue\Command;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use System\Queue\Enum\Workers;
use System\Queue\Worker\QueueWorkerInterface;

class QueueWorkerCommand extends Command
{
    public const COMMAND_NAME = 'system:queue:worker';

    public function __construct(private readonly ContainerInterface $container)
    {
        parent::__construct(self::COMMAND_NAME);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Запускает указанный воркер для обработки очередей.')
            ->addOption(
                'queue',
                null,
                InputOption::VALUE_REQUIRED,
                'Название очереди для запуска.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $queueName = $input->getOption('queue');
        $workerClass = Workers::tryFrom($queueName)?->resolveWorker();

        if ($workerClass === null) {
            $io->error(sprintf(
                'Queue %s does not exist or is not registered, stopping',
                $queueName
            ));

            return Command::INVALID;
        }

        $io->title(sprintf('Queue for work: %s', $queueName));

        try {
            $worker = $this->container->get($workerClass);

            if (!$worker instanceof QueueWorkerInterface) {
                throw new \RuntimeException(sprintf(
                    'Workers "%s" must implement %s',
                    $workerClass,
                    QueueWorkerInterface::class
                ));
            }

            $worker->execute(new ConsoleOutput());
        } catch (\Exception | ContainerExceptionInterface $e) {
            $this->handleError($io, $e);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * TODO: надо логгер
     */
    private function handleError(SymfonyStyle $io, \Throwable $e): void
    {
        $io->error(sprintf(
            'Error: %s. File: %s:%d. Trace:%s',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));
    }
}
