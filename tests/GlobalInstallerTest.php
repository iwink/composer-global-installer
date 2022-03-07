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
 * @since 1.1.0
 */
class GlobalInstallerTest extends TestCase
{
    /**
     * The composer instance.
     * @since 1.1.0
     * @var Composer
     */
    private Composer $composer;

    /**
     * The installer under test.
     * @since 1.1.0
     * @var GlobalInstaller
     */
    private GlobalInstaller $installer;

    /**
     * @inheritDoc
     * @since 1.1.0
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
            ],
        ]);
        $this->composer->setConfig($config);
        $this->installer = new GlobalInstaller(
            $io,
            $this->composer,
            '/project-dir',
            '/global-dir',
            (object)[
                'stabilities' => ['stable'],
                'exclude' => ['is-excluded'],
                'exclude-bin' => true,
            ],
        );
    }

    /**
     * Test case for {@see GlobalInstaller::supports()}.
     * @since 1.1.0
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
     * @since 1.1.0
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
     * @since 1.1.0
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
     * @since 1.1.0
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

    /**
     * Test case for {@see GlobalInstaller::getInstallPath()} with an excluded package.
     * @since 1.1.1
     */
    public function testGetInstallPathWithExcluded(): void
    {
        $package = $this->createConfiguredMock(PackageInterface::class, [
            'getPrettyName' => 'is-excluded',
            'getPrettyVersion' => '1.0.1',
            'getStability' => 'stable',
        ]);

        self::assertSame(realpath('/tmp/composer') . '/is-excluded', $this->installer->getInstallPath($package));
    }

    /**
     * Test case for {@see GlobalInstaller::getInstallPath()} with a package that has binaries.
     * @since $ver$
     * @param bool $exclude_bin Whether the installer should exclude packages with a binary.
     * @param bool $has_binary Whether the package has a binary.
     * @param bool $expects_global_dir Whether the result should return the global vendor dir.
     * @testWith [true, true, false]
     *           [null, true, true]
     *           [false, true, true]
     *           [false, false, true]
     */
    public function testGetInstallPathWithExcludedBinaries(
        ?bool $exclude_bin,
        bool $has_binary,
        bool $expects_global_dir
    ): void {
        $installer = new GlobalInstaller(
            new NullIO(),
            $this->composer,
            '/project-dir',
            '/global-dir',
            (object)[
                'stabilities' => ['stable'],
                'exclude-bin' => $exclude_bin,
            ],
        );
        $config = [
            'getPrettyName' => 'has-binaries',
            'getPrettyVersion' => '1.0.1',
            'getStability' => 'stable',
        ];

        if ($has_binary) {
            $config['getBinaries'] = ['bin/some-binary'];
        }

        $package = $this->createConfiguredMock(PackageInterface::class, $config);

        $expected_path = $expects_global_dir
            ? '/global-dir/has-binaries/1.0.1'
            : realpath('/tmp/composer') . '/has-binaries';

        self::assertSame($expected_path, $installer->getInstallPath($package));
    }
}
