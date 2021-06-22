<?php

namespace Iwink\ComposerGlobalInstaller;

use Composer\Composer;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;
use Composer\Util\ProcessExecutor;
use Composer\Util\SyncHelper;
use React\Promise\PromiseInterface;

/**
 * Global installer.
 * @since $ver$
 */
class GlobalInstaller extends LibraryInstaller {
	/**
	 * Path to global directory.
	 * @since $ver$
	 * @var string
	 */
	private string $path;

	/**
	 * Array of excluded packages (including vendor/ prefix).
	 * @since $ver$
	 * @var string[]
	 */
	private array $excludedPackages;

	/**
	 * Creates a new installer.
	 * @since $ver$
	 * @param IOInterface $io IO.
	 * @param Composer $composer Composer.
	 * @param string $path Path to global directory.
	 * @param string[] $excludedPackages Array of excluded packages.
	 */
	public function __construct(IOInterface $io, Composer $composer, string $path, array $excludedPackages) {
		parent::__construct($io, $composer, 'library', new Filesystem(new ProcessExecutor($io)));

		$this->path = $path;
		$this->excludedPackages = $excludedPackages;
	}

	/**
	 * @inheritDoc
	 * @since $ver$
	 */
	public function supports($packageType): bool {
		return !in_array($packageType, ['composer-installer', 'composer-plugin', 'metapackage']);
	}

	/**
	 * @inheritDoc
	 * @since $ver$
	 */
	public function getInstallPath(PackageInterface $package): string {
		// Path repositories are always installed local
		if ('path' === $package->getDistType()) {
			return parent::getInstallPath($package);
		}

		return $this->supportsGlobalPath($package) ? $this->getGlobalPath($package) : parent::getInstallPath($package);
	}

	/**
	 * @inheritDoc
	 *
	 * Check both global & local paths for supported packages.
	 *
	 * @since $ver$
	 */
	public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package): bool {
		if (!$this->supportsGlobalPath($package)) {
			return parent::isInstalled($repo, $package);
		}

		return parent::isInstalled($repo, $package) && Filesystem::isReadable(parent::getInstallPath($package));
	}

	/**
	 * @inheritDoc
	 *
	 * For supported packages, make sure they're installed global. The local installed packages are symlinks.
	 *
	 * @since $ver$
	 */
	public function download(PackageInterface $package, PackageInterface $prevPackage = null): ?PromiseInterface {
		if (!$this->supportsGlobalPath($package)) {
			return parent::download($package, $prevPackage);
		}

		// Make sure the package is installed global
		$path = $this->getGlobalPath($package);
		if (!Filesystem::isReadable($path)) {
			SyncHelper::downloadAndInstallPackageSync(
				$this->composer->getLoop(),
				$this->composer->getDownloadManager()->getDownloader($package->getDistType()),
				$path,
				$package
			);
		}

		// Change package to path repository and install local
		$package->setDistType('path');
		$package->setDistUrl($path);
		$package->setTransportOptions(['relative' => false]);

		return parent::download($package, $prevPackage);
	}

	/**
	 * @inheritDoc
	 *
	 * Remove symlinks to global installed packages.
	 *
	 * @since $ver$
	 */
	protected function removeCode(PackageInterface $package): ?PromiseInterface {
		$promise = parent::removeCode($package);
		if (!$this->supportsGlobalPath($package) || 'path' !== $package->getDistType()) {
			return $promise;
		}

		$path = $this->getInstallPath($package);
		if ($this->filesystem->isSymlinkedDirectory($path)) {
			if (!$promise instanceof PromiseInterface) {
				$promise = \React\Promise\resolve();
			}

			$promise->then(function () use ($package): void {
				$this->filesystem->remove($this->getInstallPath($package));
				$this->io->write(sprintf('  - %s, removing symlink', UninstallOperation::format($package)));
			});
		}

		return $promise;
	}

	/**
	 * Returns the global installer base path.
	 * @since $ver$
	 * @return string The path.
	 */
	public function getBasePath(): string {
		return $this->path;
	}

	/**
	 * Returns the global path for a package.
	 * @since $ver$
	 * @param PackageInterface $package Package.
	 * @return string The path.
	 */
	private function getGlobalPath(PackageInterface $package): ?string {
		return sprintf('%s/%s/%s', $this->getBasePath(), $package->getPrettyName(), $package->getPrettyVersion());
	}

	/**
	 * Checks if a package supports a global path.
	 * @since $ver$
	 * @param PackageInterface $package The package.
	 * @return bool If a global path is supported.
	 */
	private function supportsGlobalPath(PackageInterface $package): bool {
		if ('stable' !== $package->getStability()) {
			return false;
		}

		return !in_array($package->getPrettyName(), $this->excludedPackages, true);
	}
}
