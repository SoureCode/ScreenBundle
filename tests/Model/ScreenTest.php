<?php

namespace SoureCode\Bundle\Screen\Tests\Model;

use SoureCode\Bundle\Screen\Model\Screen;
use PHPUnit\Framework\TestCase;

class ScreenTest extends TestCase
{

    public function testConstructor(): void
    {
        // Arrange
        $expectedName = 'test_screen';
        $expectedCommand = ['ls', '-la'];

        // Act
        $actual = new Screen($expectedName, $expectedCommand);

        // Assert
        $this->assertSame($expectedName, $actual->getName());
        $this->assertSame($expectedCommand, $actual->getCommand());
    }

    public function testSetName(): void
    {
        // Arrange
        $screen = new Screen('initial', ['echo', 'foo']);
        $expected = 'new_name';

        // Act
        $screen->setName($expected);

        // Assert
        $this->assertSame($expected, $screen->getName());
    }

    public function testGetName(): void
    {
        // Arrange
        $expected = 'screen_name';
        $screen = new Screen($expected, ['cmd']);

        // Act
        $actual = $screen->getName();

        // Assert
        $this->assertSame($expected, $actual);
    }

    public function testGetCommand(): void
    {
        // Arrange
        $expected = ['php', '-v'];
        $screen = new Screen('screen', $expected);

        // Act
        $actual = $screen->getCommand();

        // Assert
        $this->assertSame($expected, $actual);
    }

    public function testSetCommand(): void
    {
        // Arrange
        $screen = new Screen('screen', ['foo']);
        $expected = ['bar', 'baz'];

        // Act
        $screen->setCommand($expected);

        // Assert
        $this->assertSame($expected, $screen->getCommand());
    }
}

