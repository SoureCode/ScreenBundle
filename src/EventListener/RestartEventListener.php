<?php

namespace SoureCode\Bundle\Screen\EventListener;

use SoureCode\Bundle\Screen\Event\ScreenSignalReceivedEvent;
use SoureCode\Bundle\Screen\Event\ScreenStoppedEvent;
use SoureCode\Bundle\Screen\Manager\ScreenManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Process\Process;

class RestartEventListener implements EventSubscriberInterface
{
    private static int $RESTART_DELAY = 3;

    private array $signals = [];

    public function __construct(private readonly ScreenManager $screenManager)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScreenSignalReceivedEvent::class => 'onScreenSignalReceived',
            ScreenStoppedEvent::class => 'onScreenStopped',
        ];
    }

    public function onScreenSignalReceived(ScreenSignalReceivedEvent $event): void
    {
        $screen = $event->getScreen();

        $this->signals[$screen->getName()] = $event->getSignal();
    }

    public function onScreenStopped(ScreenStoppedEvent $event): void
    {
        $screen = $event->getScreen();

        if ($screen->isRestartEnabled()) {
            $signal = $this->signals[$screen->getName()] ?? null;

            if (null === $signal) {
                $phpBinary = $this->screenManager->phpBinary();
                $consoleBinary = $this->screenManager->consoleBinary();

                $command = sprintf(
                    "screen -dm bash -c 'sleep %d; %s %s screen:restart %s; screen -X quit'",
                    self::$RESTART_DELAY,
                    $phpBinary,
                    $consoleBinary,
                    $screen->getName()
                );

                $process = Process::fromShellCommandline(
                    $command,
                    $this->screenManager->getBaseDirectory(),
                    null,
                    null,
                    null
                );

                $process->run();
            } else {
                unset($this->signals[$screen->getName()]);
            }
        }
    }
}