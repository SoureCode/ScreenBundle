<?php

namespace SoureCode\Bundle\Screen\Event;

use SoureCode\Bundle\Screen\Entity\ScreenInterface;
use Symfony\Component\Process\Process;
use Symfony\Contracts\EventDispatcher\Event;

final  class ScreenFailedEvent extends Event
{
    public function __construct(
        private readonly ScreenInterface $screen,
        private readonly Process $process,
        private readonly \Throwable $exception,
    ) {}

    public function getScreen(): ScreenInterface
    {
        return $this->screen;
    }

    public function getProcess(): Process
    {
        return $this->process;
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }
}