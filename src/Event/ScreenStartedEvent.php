<?php

namespace SoureCode\Bundle\Screen\Event;

use SoureCode\Bundle\Screen\Entity\ScreenInterface;
use Symfony\Component\Process\Process;
use Symfony\Contracts\EventDispatcher\Event;

final class ScreenStartedEvent extends Event
{
    public function __construct(
        private readonly ScreenInterface $screen,
        private readonly Process $process,
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
}
