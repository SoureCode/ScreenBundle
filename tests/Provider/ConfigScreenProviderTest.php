<?php

namespace SoureCode\Bundle\Screen\Tests\Provider;

use SoureCode\Bundle\Screen\Provider\ConfigScreenProvider;
use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\Screen\Factory\ScreenFactoryInterface;
use SoureCode\Bundle\Screen\Model\ScreenInterface;

class ConfigScreenProviderTest extends TestCase
{
    public function testConstructor(): void
    {
        // Arrange
        $screenFactory = $this->createMock(ScreenFactoryInterface::class);
        $screenConfigs = [
            'screen1' => ['command' => ['cmd1']],
        ];

        // Act
        $actual = new ConfigScreenProvider($screenFactory, $screenConfigs);

        // Assert
        $this->assertInstanceOf(ConfigScreenProvider::class, $actual);
    }

    public function testHas(): void
    {
        // Arrange
        $screenFactory = $this->createMock(ScreenFactoryInterface::class);
        $screenConfigs = [
            'screen1' => ['command' => ['cmd1']],
        ];
        $provider = new ConfigScreenProvider($screenFactory, $screenConfigs);

        // Act
        $actualTrue = $provider->has('screen1');
        $actualFalse = $provider->has('screen2');

        // Assert
        $this->assertTrue($actualTrue);
        $this->assertFalse($actualFalse);
    }

    public function testGet(): void
    {
        // Arrange
        $screenFactory = $this->createMock(ScreenFactoryInterface::class);
        $screenConfigs = [
            'screen1' => ['command' => ['cmd1']],
        ];
        $expectedScreen = $this->createMock(ScreenInterface::class);
        $screenFactory->expects($this->once())
            ->method('create')
            ->with('screen1', $screenConfigs['screen1'])
            ->willReturn($expectedScreen);

        $provider = new ConfigScreenProvider($screenFactory, $screenConfigs);

        // Act
        $actual = $provider->get('screen1');

        // Assert
        $this->assertSame($expectedScreen, $actual);
    }

    public function testGetThrowsExceptionForUnknownScreen(): void
    {
        // Arrange
        $screenFactory = $this->createMock(ScreenFactoryInterface::class);
        $screenConfigs = [];
        $provider = new ConfigScreenProvider($screenFactory, $screenConfigs);

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $provider->get('unknown');
    }

    public function testAll(): void
    {
        // Arrange
        $screenFactory = $this->createMock(ScreenFactoryInterface::class);
        $screenConfigs = [
            'screen1' => ['command' => ['cmd1']],
            'screen2' => ['command' => ['cmd2']],
        ];
        $expectedScreen1 = $this->createMock(ScreenInterface::class);
        $expectedScreen2 = $this->createMock(ScreenInterface::class);

        $screenFactory->expects($this->exactly(2))
            ->method('create')
            ->withConsecutive(
                ['screen1', $screenConfigs['screen1']],
                ['screen2', $screenConfigs['screen2']]
            )
            ->willReturnOnConsecutiveCalls($expectedScreen1, $expectedScreen2);

        $provider = new ConfigScreenProvider($screenFactory, $screenConfigs);

        // Act
        $actual = $provider->all();

        // Assert
        $expected = [
            'screen1' => $expectedScreen1,
            'screen2' => $expectedScreen2,
        ];
        $this->assertSame($expected, $actual);
    }
}

