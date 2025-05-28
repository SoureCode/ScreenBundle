<?php

namespace SoureCode\Bundle\Screen\Event;

use SoureCode\Bundle\Screen\Entity\ScreenInterface;
use Symfony\Component\Process\Process;
use Symfony\Contracts\EventDispatcher\Event;

final class ScreenStoppedEvent extends Event
{
    public function __construct(
        private readonly ScreenInterface $screen,
        private readonly Process $process,
        private readonly int $exitCode,
    ) {
    }

    public function getScreen(): ScreenInterface
    {
        return $this->screen;
    }

    public function getProcess(): Process
    {
        return $this->process;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }
}
