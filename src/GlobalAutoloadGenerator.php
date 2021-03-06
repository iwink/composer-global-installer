<?php

declare(strict_types=1);

namespace Iwink\ComposerGlobalInstaller;

use Composer\Autoload\AutoloadGenerator;
use Composer\Config;
use Composer\Installer\InstallationManager;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;

/**
 * Autoload generator for globally installed packages.
 * @since 1.0.0
 */
class GlobalAutoloadGenerator extends AutoloadGenerator
{
    /**
     * Array of globally installed package paths.
     * @since 1.0.0
     * @var string[]
     */
    private array $globalPaths = [];

    /**
     * @inheritDoc
     *
     * Builds an array of globally installed package-paths which is used to determine the autoloader paths.
     *
     * @since 1.0.0
     */
    public function dump(
        Config $config,
        InstalledRepositoryInterface $localRepo,
        RootPackageInterface $rootPackage,
        InstallationManager $installationManager,
        $targetDir,
        $scanPsrPackages = false,
        $suffix = ''
    ): int {
        $this->globalPaths = array_map(
            static fn(PackageInterface $p): string => $p->getDistUrl(),
            array_filter(
                $localRepo->getCanonicalPackages(),
                static function (PackageInterface $p) use ($installationManager): bool {
                    // We can skip if the installer doesn't support the package
                    $installer = $installationManager->getInstaller($p->getType());
                    if (!$installer instanceof GlobalInstaller || !$installer->supports($p->getType())) {
                        return false;
                    }

                    // We can skip if the package isn't an absolute symlink
                    if ('path' !== $p->getDistType() || ($p->getTransportOptions()['relative'] ?? true)) {
                        return false;
                    }

                    // We can skip if the dist URL isn't our global installer path
                    if (0 !== strpos($p->getDistUrl(), $installer->getBasePath())) {
                        return false;
                    }

                    return true;
                }
            )
        );

        return parent::dump(
            $config,
            $localRepo,
            $rootPackage,
            $installationManager,
            $targetDir,
            $scanPsrPackages,
            $suffix
        );
    }

    /**
     * @inheritDoc
     *
     * If the requested path is a valid global install path, return it without modification.
     *
     * @since 1.0.0
     */
    protected function getPathCode(Filesystem $filesystem, $basePath, $vendorPath, $path): string
    {
        $resolvedPath = realpath($path);
        if (false === $resolvedPath) {
            throw new \InvalidArgumentException(sprintf('Could not resolve path "%s"', $path));
        }

        foreach ($this->globalPaths as $globalPath) {
            if (0 === strpos($resolvedPath, $globalPath)) {
                return var_export($filesystem->normalizePath($resolvedPath), true);
            }
        }

        return parent::getPathCode($filesystem, $basePath, $vendorPath, $path);
    }
}
