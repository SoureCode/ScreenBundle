<?php

namespace SoureCode\Bundle\Screen;

use Doctrine\ORM\EntityManagerInterface;
use SoureCode\Bundle\Screen\Command\ScreenAttachCommand;
use SoureCode\Bundle\Screen\Command\ScreenKillCommand;
use SoureCode\Bundle\Screen\Command\ScreenLogCommand;
use SoureCode\Bundle\Screen\Command\ScreenStartCommand;
use SoureCode\Bundle\Screen\Command\ScreenStatusCommand;
use SoureCode\Bundle\Screen\Command\ScreenStopCommand;
use SoureCode\Bundle\Screen\Factory\ScreenFactory;
use SoureCode\Bundle\Screen\Factory\ScreenFactoryInterface;
use SoureCode\Bundle\Screen\Manager\ScreenManager;
use SoureCode\Bundle\Screen\Model\Screen;
use SoureCode\Bundle\Screen\Model\ScreenInterface;
use SoureCode\Bundle\Screen\Provider\ChainScreenProvider;
use SoureCode\Bundle\Screen\Provider\ConfigScreenProvider;
use SoureCode\Bundle\Screen\Provider\DoctrineScreenProvider;
use SoureCode\Bundle\Screen\Provider\ScreenProviderInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

class SoureCodeScreenBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        // @formatter:off
        $definition->rootNode()
            ->fixXmlConfig('screen')
            ->children()
                ->scalarNode('class')
                    ->defaultValue(Screen::class)
                    ->validate()
                        ->ifTrue(fn ($v) => !is_a($v, ScreenInterface::class, true))
                        ->thenInvalid('The class must be an instance of "%s".')
                    ->end()
                ->end()
                ->arrayNode('screens')
                    ->beforeNormalization()->castToArray()->end()
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('command')
                                ->beforeNormalization()->castToArray()->end()
                                ->scalarPrototype()->end()
                                ->isRequired()
                                ->validate()
                                    ->ifTrue(fn ($v) => !is_array($v) || count($v) <= 0)
                                    ->thenInvalid('The command must be an array with at least one element.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ;
        // @formatter:on
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(ScreenProviderInterface::class)
            ->addTag('soure_code.screen.provider');

        $services = $container->services();
        $prefix = 'soure_code.screen.';

        $services->set($prefix . 'factory', ScreenFactory::class)
            ->args([
                $config['class'],
            ]);

        $services->alias(ScreenFactoryInterface::class, $prefix . 'factory')
            ->public();

        $bundles = $builder->getParameter('kernel.bundles');

        if (array_key_exists('DoctrineBundle', $bundles))
        {
            $services->set($prefix . 'provider.doctrine', DoctrineScreenProvider::class)
                ->args([
                    $config['class'],
                    service(EntityManagerInterface::class),
                ])
                ->tag('soure_code.screen.provider');
        }

        $services->set($prefix . 'provider.config', ConfigScreenProvider::class)
            ->args([
                service($prefix . 'factory'),
                $config['screens'],
            ])
            ->tag('soure_code.screen.provider');

        $services->set($prefix . 'provider.chain', ChainScreenProvider::class)
            ->args([
                tagged_iterator('soure_code.screen.provider'),
            ]);

        $services->alias(ScreenProviderInterface::class, $prefix . 'provider.chain')
            ->public();

        $services->set($prefix . 'manager', ScreenManager::class)
            ->args([
                param('kernel.project_dir'),
                service(Filesystem::class),
                service($prefix . 'provider.chain'),
            ]);

        $services->alias(ScreenManager::class, $prefix . 'manager')
            ->public();

        $services->set($prefix . 'command.attach', ScreenAttachCommand::class)
            ->args([
                service($prefix . 'provider.chain'),
                service($prefix . 'manager'),
            ])
            ->tag('console.command', [
                'command' => 'screen:attach',
            ]);

        $services->set($prefix . 'command.kill', ScreenKillCommand::class)
            ->args([
                service($prefix . 'provider.chain'),
                service($prefix . 'manager'),
            ])
            ->tag('console.command', [
                'command' => 'screen:kill',
            ]);

        $services->set($prefix . 'command.log', ScreenLogCommand::class)
            ->args([
                service($prefix . 'provider.chain'),
                service($prefix . 'manager'),
            ])
            ->tag('console.command', [
                'command' => 'screen:log',
            ]);

        $services->set($prefix . 'command.start', ScreenStartCommand::class)
            ->args([
                service($prefix . 'provider.chain'),
                service($prefix . 'manager'),
            ])
            ->tag('console.command', [
                'command' => 'screen:start',
            ]);

        $services->set($prefix . 'command.status', ScreenStatusCommand::class)
            ->args([
                service($prefix . 'provider.chain'),
                service($prefix . 'manager'),
            ])
            ->tag('console.command', [
                'command' => 'screen:status',
            ]);

        $services->set($prefix . 'command.stop', ScreenStopCommand::class)
            ->args([
                service($prefix . 'provider.chain'),
                service($prefix . 'manager'),
            ])
            ->tag('console.command', [
                'command' => 'screen:stop',
            ]);
    }

    public function build(ContainerBuilder $container): void
    {
        if (!shell_exec('which screen')) {
            throw new \RuntimeException('GNU Screen is not installed. Install it with `apt install screen`.');
        }

        if (!preg_match('/^Screen version \d+\.\d+\.\d+ \(GNU\)/', shell_exec('screen -v'))) {
            throw new \RuntimeException('The screen command is not the GNU Screen.');
        }

        parent::build($container);
    }
}