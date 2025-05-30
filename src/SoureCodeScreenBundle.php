<?php

namespace SoureCode\Bundle\Screen;

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
use SoureCode\Bundle\Screen\Provider\ArrayScreenProvider;
use SoureCode\Bundle\Screen\Provider\ChainScreenProvider;
use SoureCode\Bundle\Screen\Provider\ScreenProviderInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
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
        /**
         * @var ArrayNodeDefinition $rootNode
         */
        $rootNode = $definition->rootNode();

        // @formatter:off
        $screenConfig = $rootNode->fixXmlConfig('screen')->children();

        $screenConfig
            ->scalarNode('class')
                ->defaultValue(Screen::class)
                ->validate()
                    ->ifTrue(fn (string $v) => !is_a($v, ScreenInterface::class, true))
                    ->thenInvalid('The class must be an instance of "%s".');

        $screenConfig
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
                                ->ifTrue(fn ($v) => !\is_array($v) || \count($v) <= 0)
                                ->thenInvalid('The command must be an array with at least one element.')
                            ->end()
                        ->end()
                        ->scalarNode('restart')
                            ->setDeprecated('sourecode/screen-bundle', 'dev', 'The "restart" option is deprecated and will be removed in 1.0. Use external tools to just call start every several minutes.')
                            ->defaultValue(false)
                            ->info('If the screen should be restarted when it exits.')
                            ->validate()
                                ->ifTrue(fn ($v) => !\is_bool($v))
                                ->thenInvalid('The restart option must be a boolean.')
        ;
        // @formatter:on
    }

    /**
     * @param array{class: class-string<ScreenInterface>, screens: array<string, array{command: array<string>}>} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(ScreenProviderInterface::class)
            ->addTag('soure_code.screen.provider');

        $services = $container->services();

        $services->set(self::$PREFIX.'factory', ScreenFactory::class)
            ->args([
                $config['class'],
            ]);

        $services->alias(ScreenFactoryInterface::class, self::$PREFIX.'factory')
            ->public();

        $services->set(self::$PREFIX.'provider.config', ArrayScreenProvider::class)
            ->args([
                service(self::$PREFIX.'factory'),
                $config['screens'],
            ])
            ->tag('soure_code.screen.provider');

        $services->set(self::$PREFIX.'provider.chain', ChainScreenProvider::class)
            ->args([
                tagged_iterator('soure_code.screen.provider'),
            ]);

        $services->alias(ScreenProviderInterface::class, self::$PREFIX.'provider.chain')
            ->public();

        $services->set(self::$PREFIX.'manager', ScreenManager::class)
            ->args([
                param('kernel.project_dir'),
                param('kernel.environment'),
                service(Filesystem::class),
                service(self::$PREFIX.'provider.chain'),
                service('logger'),
            ]);

        $services->alias(ScreenManager::class, self::$PREFIX.'manager')
            ->public();

        $services->set(self::$PREFIX.'command.attach', ScreenAttachCommand::class)
            ->args([
                service(self::$PREFIX.'provider.chain'),
                service(self::$PREFIX.'manager'),
            ])
            ->tag('console.command', [
                'command' => 'screen:attach',
            ]);

        $services->set(self::$PREFIX.'command.kill', ScreenKillCommand::class)
            ->args([
                service(self::$PREFIX.'provider.chain'),
                service(self::$PREFIX.'manager'),
            ])
            ->tag('console.command', [
                'command' => 'screen:kill',
            ]);

        $services->set(self::$PREFIX.'command.log', ScreenLogCommand::class)
            ->args([
                service(self::$PREFIX.'provider.chain'),
                service(self::$PREFIX.'manager'),
            ])
            ->tag('console.command', [
                'command' => 'screen:log',
            ]);

        $services->set(self::$PREFIX.'command.start', ScreenStartCommand::class)
            ->args([
                service(self::$PREFIX.'provider.chain'),
                service(self::$PREFIX.'manager'),
            ])
            ->tag('console.command', [
                'command' => 'screen:start',
            ]);

        $services->set(self::$PREFIX.'command.restart', ScreenStartCommand::class)
            ->args([
                service(self::$PREFIX.'provider.chain'),
                service(self::$PREFIX.'manager'),
            ])
            ->tag('console.command', [
                'command' => 'screen:restart',
            ]);

        $services->set(self::$PREFIX.'command.status', ScreenStatusCommand::class)
            ->args([
                service(self::$PREFIX.'provider.chain'),
                service(self::$PREFIX.'manager'),
            ])
            ->tag('console.command', [
                'command' => 'screen:status',
            ]);

        $services->set(self::$PREFIX.'command.stop', ScreenStopCommand::class)
            ->args([
                service(self::$PREFIX.'provider.chain'),
                service(self::$PREFIX.'manager'),
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

        $result = shell_exec('screen -v');

        if (!$result) {
            throw new \RuntimeException('The screen command is not available. Make sure it is installed and in your PATH.');
        }

        if (!preg_match('/^Screen version \d+\.\d+\.\d+ \(GNU\)/', $result)) {
            throw new \RuntimeException('The screen command is not the GNU Screen.');
        }

        parent::build($container);
    }
}
