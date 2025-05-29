<?php

namespace SoureCode\Bundle\Screen\Tests\Factory;

use SoureCode\Bundle\Screen\Factory\ScreenFactory;
use SoureCode\Bundle\Screen\Model\Screen;
use PHPUnit\Framework\TestCase;

class ScreenFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        // Arrange
        $expectedName = 'test_screen';
        $expectedCommand = ['ls', '-la'];
        $factory = new ScreenFactory(Screen::class);
        $config = ['command' => $expectedCommand];

        // Act
        $actual = $factory->create($expectedName, $config);

        // Assert
        $this->assertSame($expectedName, $actual->getName());
        $this->assertSame($expectedCommand, $actual->getCommand());
    }

    public function testConstructor(): void
    {
        // Arrange
        $expectedClassName = Screen::class;

        // Act
        $factory = new ScreenFactory($expectedClassName);

        // Assert
        $reflection = new \ReflectionClass($factory);
        $property = $reflection->getProperty('screenClassName');
        $property->setAccessible(true);
        $actual = $property->getValue($factory);

        $this->assertSame($expectedClassName, $actual);
    }
}

