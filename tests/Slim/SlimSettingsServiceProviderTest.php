<?php

namespace Chubbyphp\Tests\Config;

use Chubbyphp\Config\ConfigInterface;
use Chubbyphp\Config\ConfigProviderInterface;
use Chubbyphp\Config\Slim\SlimSettingsInterface;
use Chubbyphp\Config\Slim\SlimSettingsServiceProvider;
use Chubbyphp\Mock\Call;
use Chubbyphp\Mock\MockByCallsTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Slim\Collection;
use Chubbyphp\Config\ConfigException;

/**
 * @covers \Chubbyphp\Config\Slim\SlimSettingsServiceProvider
 */
class SlimSettingsServiceProviderTest extends TestCase
{
    use MockByCallsTrait;

    public function testRegisterWithConfigInterface()
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Missing interface "Chubbyphp\Config\Slim\SlimSettingsInterface"');

        $container = new Container([
            'environment' => 'dev',
            'settings' => function () {
                return new Collection(['displayErrorDetails' => false]);
            },
        ]);

        /** @var ConfigInterface|MockObject $config */
        $config = $this->getMockByCalls(ConfigInterface::class);

        /** @var ConfigProviderInterface|MockObject $provider */
        $provider = $this->getMockByCalls(ConfigProviderInterface::class, [
            Call::create('get')->with('dev')->willReturn($config),
        ]);

        $serviceProvider = new SlimSettingsServiceProvider($provider);
        $serviceProvider->register($container);
    }

    public function testRegisterWithSlimSettingsInterface()
    {
        $container = new Container([
            'environment' => 'dev',
            'settings' => function () {
                return new Collection(['displayErrorDetails' => false]);
            },
        ]);

        /** @var SlimSettingsInterface|MockObject $config */
        $config = $this->getMockByCalls(SlimSettingsInterface::class, [
            Call::create('getSlimSettings')->with()->willReturn(['displayErrorDetails' => true]),
        ]);

        /** @var ConfigProviderInterface|MockObject $provider */
        $provider = $this->getMockByCalls(ConfigProviderInterface::class, [
            Call::create('get')->with('dev')->willReturn($config),
        ]);

        $serviceProvider = new SlimSettingsServiceProvider($provider);
        $serviceProvider->register($container);

        self::assertSame(['displayErrorDetails' => true], $container['settings']->all());
    }
}
