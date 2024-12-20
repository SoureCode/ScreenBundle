<?php

namespace SoureCode\Bundle\Screen;

use Doctrine\ORM\EntityManagerInterface;
use SoureCode\Bundle\Screen\Command\ScreenAttachCommand;
use SoureCode\Bundle\Screen\Command\ScreenKillCommand;
use SoureCode\Bundle\Screen\Command\ScreenLogCommand;
use SoureCode\Bundle\Screen\Command\ScreenRunCommand;
use SoureCode\Bundle\Screen\Command\ScreenStartCommand;
use SoureCode\Bundle\Screen\Command\ScreenStatusCommand;
use SoureCode\Bundle\Screen\Command\ScreenStopCommand;
use SoureCode\Bundle\Screen\EventListener\RestartEventListener;
use SoureCode\Bundle\Screen\Factory\ScreenFactory;
use SoureCode\Bundle\Screen\Factory\ScreenFactoryInterface;
use SoureCode\Bundle\Screen\Manager\ScreenManager;
use SoureCode\Bundle\Screen\Entity\Screen;
use SoureCode\Bundle\Screen\Entity\ScreenInterface;
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
    private static string $PREFIX = 'soure_code.screen.';

    public function configure(DefinitionConfigurator $definition): void
    {
        // @formatter:off
        $definition->rootNode()
            ->fixXmlConfig('screen')
            ->children()
                ->scalarNode('doctrine')
                    ->defaultValue(false)
                    ->validate()
                        ->ifTrue(fn ($v) => !is_bool($v))
                        ->thenInvalid('The doctrine option must be a boolean.')
                    ->end()
                ->end()
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
                            ->scalarNode('restart')
                                ->defaultValue(false)
                                ->info('If the screen should be restarted when it exits.')
                                ->validate()
                                    ->ifTrue(fn ($v) => !is_bool($v))
                                    ->thenInvalid('The restart option must be a boolean.')
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
        $parameters = $container->parameters();

        $bundles = $builder->getParameter('kernel.bundles');
        $doctrineEnabled = $config['doctrine'] && array_key_exists('DoctrineBundle', $bundles);

        $services->set(self::$PREFIX . 'factory', ScreenFactory::class)
            ->args([
                $config['class'],
            ]);

        $services->alias(ScreenFactoryInterface::class, self::$PREFIX . 'factory')
            ->public();

        if ($doctrineEnabled) {
            $services->set(self::$PREFIX . 'provider.doctrine', DoctrineScreenProvider::class)
                ->args([
                    $config['class'],
                    service(EntityManagerInterface::class),
                ])
                ->tag('soure_code.screen.provider');
        }

        $services->set(self::$PREFIX . 'provider.config', ConfigScreenProvider::class)
            ->args([
                service(self::$PREFIX . 'factory'),
                $config['screens'],
            ])
            ->tag('soure_code.screen.provider');

        $services->set(self::$PREFIX . 'provider.chain', ChainScreenProvider::class)
            ->args([
                tagged_iterator('soure_code.screen.provider'),
            ]);

        $services->alias(ScreenProviderInterface::class, self::$PREFIX . 'provider.chain')
            ->public();

        $services->set(self::$PREFIX . 'manager', ScreenManager::class)
            ->args([
                param('kernel.project_dir'),
                param('kernel.environment'),
                service(Filesystem::class),
                service(self::$PREFIX . 'provider.chain'),
                service('logger'),
            ]);

        $services->alias(ScreenManager::class, self::$PREFIX . 'manager')
            ->public();

        $services->set(self::$PREFIX . 'command.run', ScreenRunCommand::class)
            ->args([
                service(self::$PREFIX . 'provider.chain'),
                service('event_dispatcher'),
                param('kernel.project_dir'),
                param('kernel.environment'),
            ])
            ->tag('console.command', [
                'command' => 'screen:run',
                'hidden' => true,
            ]);

        $services->set(self::$PREFIX . 'command.attach', ScreenAttachCommand::class)
            ->args([
                service(self::$PREFIX . 'provider.chain'),
                service(self::$PREFIX . 'manager'),
            ])
            ->tag('console.command', [
                'command' => 'screen:attach',
            ]);

        $services->set(self::$PREFIX . 'command.kill', ScreenKillCommand::class)
            ->args([
                service(self::$PREFIX . 'provider.chain'),
                service(self::$PREFIX . 'manager'),
            ])
            ->tag('console.command', [
                'command' => 'screen:kill',
            ]);

        $services->set(self::$PREFIX . 'command.log', ScreenLogCommand::class)
            ->args([
                service(self::$PREFIX . 'provider.chain'),
                service(self::$PREFIX . 'manager'),
            ])
            ->tag('console.command', [
                'command' => 'screen:log',
            ]);

        $services->set(self::$PREFIX . 'command.start', ScreenStartCommand::class)
            ->args([
                service(self::$PREFIX . 'provider.chain'),
                service(self::$PREFIX . 'manager'),
            ])
            ->tag('console.command', [
                'command' => 'screen:start',
            ]);

        $services->set(self::$PREFIX . 'command.restart', ScreenStartCommand::class)
            ->args([
                service(self::$PREFIX . 'provider.chain'),
                service(self::$PREFIX . 'manager'),
            ])
            ->tag('console.command', [
                'command' => 'screen:restart',
            ]);

        $services->set(self::$PREFIX . 'command.status', ScreenStatusCommand::class)
            ->args([
                service(self::$PREFIX . 'provider.chain'),
                service(self::$PREFIX . 'manager'),
            ])
            ->tag('console.command', [
                'command' => 'screen:status',
            ]);

        $services->set(self::$PREFIX . 'command.stop', ScreenStopCommand::class)
            ->args([
                service(self::$PREFIX . 'provider.chain'),
                service(self::$PREFIX . 'manager'),
            ])
            ->tag('console.command', [
                'command' => 'screen:stop',
            ]);

        $services->set(self::$PREFIX . 'event_listener.restart', RestartEventListener::class)
            ->args([
                service(self::$PREFIX . 'manager'),
            ])
            ->tag('kernel.event_subscriber');
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

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $bundles = $builder->getParameter('kernel.bundles');
        $doctrineEnabled = array_key_exists('DoctrineBundle', $bundles);

        if ($doctrineEnabled) {
            $container->extension('doctrine', [
                'orm' => [
                    'mappings' => [
                        'SoureCodeScreenBundle' => [
                            'is_bundle' => true,
                            'type' => 'xml',
                            'dir' => 'config/doctrine',
                            'prefix' => 'SoureCode\Bundle\Screen\Entity',
                            'alias' => 'SoureCodeScreen',
                        ],
                    ],
                ],
            ]);
        }

        parent::prependExtension($container, $builder); // TODO: Change the autogenerated stub
    }
}

