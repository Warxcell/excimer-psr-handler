<?php

declare(strict_types=1);

namespace Warxcell\ExcimerHandler;

use ExcimerProfiler;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function sprintf;

use const EXCIMER_REAL;

final class ExcimerCommandHandler implements EventSubscriberInterface
{
    private ?ExcimerProfiler $profiler = null;

    public function __construct(
        private readonly SpeedscopeDataSender $speedscopeDataSender,
        private readonly LoggerInterface $logger,
        private readonly bool $enabled
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onCommand',
            ConsoleEvents::TERMINATE => 'onTerminate',
        ];
    }

    public function onCommand(): void
    {
        if (!$this->enabled) {
            return;
        }
        $this->profiler = new ExcimerProfiler();
        $this->profiler->setPeriod(0.001); // 1ms
        $this->profiler->setEventType(EXCIMER_REAL);
        $this->profiler->start();
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        if (!$this->profiler) {
            return;
        }

        $this->profiler->stop();
        $data = $this->profiler->getLog()->getSpeedscopeData();

        try {
            ($this->speedscopeDataSender)(
                name: sprintf(
                    'bin/console %s',
                    $event->getCommand()->getName() ?? 'Unknown command'
                ),
                data: $data
            );
        } catch (ClientExceptionInterface|JsonException $exception) {
            $this->logger->error(
                $exception->getMessage(),
                [
                    'exception' => $exception,
                ]
            );
        } finally {
            $this->profiler = null;
        }
    }
}
