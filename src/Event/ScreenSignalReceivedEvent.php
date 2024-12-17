<?php

namespace SoureCode\Bundle\Screen\Event;

use SoureCode\Bundle\Screen\Entity\ScreenInterface;
use Symfony\Component\Process\Process;
use Symfony\Contracts\EventDispatcher\Event;

final  class ScreenSignalReceivedEvent extends Event
{
    public function __construct(
        private readonly ScreenInterface $screen,
        private readonly Process $process,
        private readonly int $signal,
    ) {}

    public function getScreen(): ScreenInterface
    {
        return $this->screen;
    }

    public function getProcess(): Process
    {
        return $this->process;
    }

    public function getSignal(): int
    {
        return $this->signal;
    }
}