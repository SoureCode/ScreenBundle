<?php

namespace SoureCode\Bundle\Screen\Model;

interface ScreenInterface
{
    public function getName(): string;

    /**
     * @return list<string>
     */
    public function getCommand(): array;
}