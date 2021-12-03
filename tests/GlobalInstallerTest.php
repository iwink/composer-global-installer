<?php

namespace Iwink\ComposerGlobalInstaller\Tests;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Package\PackageInterface;
use Iwink\ComposerGlobalInstaller\GlobalInstaller;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see GlobalInstaller}.
 * @since $ver$
 */
class GlobalInstallerTest extends TestCase
{
    /**
     * The composer instance.
     * @since $ver$
     * @var Composer
     */
    private Composer $composer;

    /**
     * The installer under test.
     * @since $ver$
     * @var GlobalInstaller
     */
    private GlobalInstaller $installer;

    /**
     * @inheritDoc
     * @since $ver$
     */
    protected function setUp(): void
    {
        parent::setUp();

        $io = new NullIO();

        $this->composer = new Composer();
        $config = Factory::createConfig($io);
        $config->merge([
            'config' => [
                'vendor-dir' => '/tmp/composer',
            ]
        ]);
        $this->composer->setConfig($config);
        $this->installer = new GlobalInstaller(
            $io,
            $this->composer,
            '/project-dir',
            '/global-dir',
            (object)[
                'stabilities' => ['stable'],
            ],
        );
    }

    /**
     * Test case for {@see GlobalInstaller::supports()}.
     * @since $ver$
     * @testWith ["composer-installer"]
     *           ["composer-plugin"]
     *           ["metapackage"]
     */
    public function testSupports(string $package): void
    {
        self::assertFalse($this->installer->supports($package));
    }

    /**
     * Test case for {@see GlobalInstaller::getInstallPath()} with allowed path.
     * @since $ver$
     */
    public function testGetInstallPathWithAllowed(): void
    {
        $package = $this->createConfiguredMock(PackageInterface::class, [
            'getPrettyName' => 'my-package',
            'getPrettyVersion' => '1.0.1',
            'getStability' => 'stable',
        ]);

        self::assertSame('/global-dir/my-package/1.0.1', $this->installer->getInstallPath($package));
    }

    /**
     * Test case for {@see GlobalInstaller::getInstallPath()} with non-allowed path.
     * @since $ver$
     */
    public function testGetInstallPathWithoutAllowed(): void
    {
        $package = $this->createConfiguredMock(PackageInterface::class, [
            'getPrettyName' => 'my-package',
            'getPrettyVersion' => '1.0.1',
            'getStability' => 'dev',
        ]);

        self::assertSame(realpath('/tmp/composer') . '/my-package', $this->installer->getInstallPath($package));
    }

    /**
     * Test case for {@see GlobalInstaller::getInstallPath()} with a `path` type.
     * @since $ver$
     */
    public function testGetInstallPathWithPath(): void
    {
        $package = $this->createConfiguredMock(PackageInterface::class, [
            'getDistType' => 'path',
            'getPrettyName' => 'my-package',
            'getPrettyVersion' => '1.0.1',
            'getStability' => 'stable',
        ]);

        self::assertSame(realpath('/tmp/composer') . '/my-package', $this->installer->getInstallPath($package));
    }
}
