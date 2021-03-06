<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\Config\Unit\ServiceProvider;

use Chubbyphp\Config\ConfigInterface;
use Chubbyphp\Config\ConfigProviderInterface;
use Chubbyphp\Config\ServiceProvider\ConfigServiceProvider;
use Chubbyphp\Mock\Call;
use Chubbyphp\Mock\MockByCallsTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

/**
 * @covers \Chubbyphp\Config\ServiceProvider\ConfigServiceProvider
 *
 * @internal
 */
final class ConfigServiceProviderTest extends TestCase
{
    use MockByCallsTrait;

    public function testRegisterWithNull(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage(
            'Chubbyphp\Config\ServiceProvider\ConfigServiceProvider::__construct() expects parameter 1 to be '
                .'Chubbyphp\Config\ConfigInterface|Chubbyphp\Config\ConfigProviderInterface, NULL given'
        );

        $container = new Container();
        $container->register(new ConfigServiceProvider(null));
    }

    public function testRegisterWithStdClass(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage(
            'Chubbyphp\Config\ServiceProvider\ConfigServiceProvider::__construct() expects parameter 1 to be '
                .'Chubbyphp\Config\ConfigInterface|Chubbyphp\Config\ConfigProviderInterface, stdClass given'
        );

        $container = new Container();
        $container->register(new ConfigServiceProvider(new \stdClass()));
    }

    public function testRegisterWithProvider(): void
    {
        $container = new Container(['env' => 'dev']);

        $directory = sys_get_temp_dir().'/config-service-provider-'.uniqid();

        $directories = ['sample' => $directory];

        /** @var ConfigInterface|MockObject $config */
        $config = $this->getMockByCalls(ConfigInterface::class, [
            Call::create('getConfig')->with()->willReturn(['key' => 'value']),
            Call::create('getDirectories')->with()->willReturn($directories),
        ]);

        /** @var ConfigProviderInterface|MockObject $provider */
        $provider = $this->getMockByCalls(ConfigProviderInterface::class, [
            Call::create('get')->with('dev')->willReturn($config),
        ]);

        self::assertDirectoryNotExists($directory);

        error_clear_last();

        $container->register(new ConfigServiceProvider($provider));

        $error = error_get_last();

        self::assertNotNull($error);

        self::assertSame(E_USER_DEPRECATED, $error['type']);
        self::assertSame(
            sprintf(
                'Use "%s" instead of "%s" as __construct argument',
                ConfigInterface::class,
                ConfigProviderInterface::class
            ),
            $error['message']
        );

        self::assertArrayHasKey('key', $container);
        self::assertSame('value', $container['key']);

        self::assertArrayHasKey('chubbyphp.config.directories', $container);

        self::assertSame($directories, $container['chubbyphp.config.directories']);

        self::assertDirectoryExists($directory);
    }

    public function testRegister(): void
    {
        $container = new Container();

        $directory = sys_get_temp_dir().'/config-service-provider-'.uniqid();

        $directories = ['sample' => $directory];

        /** @var ConfigInterface|MockObject $config */
        $config = $this->getMockByCalls(ConfigInterface::class, [
            Call::create('getConfig')->with()->willReturn(['key' => 'value']),
            Call::create('getDirectories')->with()->willReturn($directories),
        ]);

        self::assertDirectoryNotExists($directory);

        $container->register(new ConfigServiceProvider($config));

        self::assertArrayHasKey('key', $container);
        self::assertSame('value', $container['key']);

        self::assertArrayHasKey('chubbyphp.config.directories', $container);

        self::assertSame($directories, $container['chubbyphp.config.directories']);

        self::assertDirectoryExists($directory);
    }

    public function testRegisterWithExistingScalar(): void
    {
        $container = new Container([
            'key' => 'existingValue',
        ]);

        $directory = sys_get_temp_dir().'/config-service-provider-'.uniqid();

        $directories = ['sample' => $directory];

        /** @var ConfigInterface|MockObject $config */
        $config = $this->getMockByCalls(ConfigInterface::class, [
            Call::create('getConfig')->with()->willReturn(['key' => 'value']),
            Call::create('getDirectories')->with()->willReturn($directories),
        ]);

        self::assertDirectoryNotExists($directory);

        $container->register(new ConfigServiceProvider($config));

        self::assertArrayHasKey('key', $container);
        self::assertSame('value', $container['key']);

        self::assertDirectoryExists($directory);
    }

    public function testRegisterWithExistingArray(): void
    {
        $container = new Container([
            'key' => [
                'key1' => [
                    'key11' => 'value11',
                    'key12' => 'value12',
                ],
                'key2' => 'value2',
                'key3' => [
                    0 => 'value31',
                    2 => 'value32',
                ],
            ],
        ]);

        $directory = sys_get_temp_dir().'/config-service-provider-'.uniqid();

        $directories = ['sample' => $directory];

        /** @var ConfigInterface|MockObject $config */
        $config = $this->getMockByCalls(ConfigInterface::class, [
            Call::create('getConfig')->with()->willReturn([
                'env' => 'test',
                'key' => [
                    'key1' => [
                        'key12' => 'value112',
                    ],
                    'key3' => [
                        'value33',
                        'value34',
                    ],
                    'key4' => 'value4',
                ],
            ]),
            Call::create('getDirectories')->with()->willReturn($directories),
        ]);

        self::assertDirectoryNotExists($directory);

        $container->register(new ConfigServiceProvider($config));

        self::assertArrayHasKey('env', $container);

        self::assertSame('test', $container['env']);

        self::assertArrayHasKey('key', $container);
        self::assertSame([
            'key1' => [
                'key11' => 'value11',
                'key12' => 'value112',
            ],
            'key2' => 'value2',
            'key3' => [
                0 => 'value31',
                2 => 'value32',
                3 => 'value33',
                4 => 'value34',
            ],
            'key4' => 'value4',
        ], $container['key']);

        self::assertDirectoryExists($directory);

        self::assertSame('0775', substr(sprintf('%o', fileperms($directory)), -4));
    }

    public function testRegisterWithExistingStringConvertToInt(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Type conversion from "string" to "integer" at path "key"');

        $container = new Container([
            'key' => 'value',
        ]);

        $directory = sys_get_temp_dir().'/config-service-provider-'.uniqid();

        /** @var ConfigInterface|MockObject $config */
        $config = $this->getMockByCalls(ConfigInterface::class, [
            Call::create('getConfig')->with()->willReturn(['key' => 1]),
        ]);

        self::assertDirectoryNotExists($directory);

        $container->register(new ConfigServiceProvider($config));
    }

    public function testRegisterWithExistingStringConvertToArray(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Type conversion from "string" to "array" at path "key.key1.key12"');

        $container = new Container([
            'key' => [
                'key1' => [
                    'key11' => 'value11',
                    'key12' => 'value12',
                ],
                'key2' => 'value2',
                'key3' => [
                    0 => 'value31',
                    2 => 'value32',
                ],
            ],
        ]);

        $directory = sys_get_temp_dir().'/config-service-provider-'.uniqid();

        /** @var ConfigInterface|MockObject $config */
        $config = $this->getMockByCalls(ConfigInterface::class, [
            Call::create('getConfig')->with()->willReturn([
                'key' => [
                    'key1' => [
                        'key12' => ['value112'],
                    ],
                    'key3' => [
                        'value33',
                    ],
                ],
            ]),
        ]);

        self::assertDirectoryNotExists($directory);

        $container->register(new ConfigServiceProvider($config));
    }
}
