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
 * @since 1.0.0
 */
class GlobalInstaller extends LibraryInstaller {
	/**
	 * Path to project directory.
	 * @since 1.0.0
	 * @var string
	 */
	private string $projectPath;

	/**
	 * Path to global directory.
	 * @since 1.0.0
	 * @var string
	 */
	private string $globalPath;

	/**
	 * Array of excluded packages (including vendor/ prefix).
	 * @since 1.0.0
	 * @var string[]
	 */
	private array $excludedPackages;

	/**
	 * Creates a new installer.
	 * @since 1.0.0
	 * @param IOInterface $io IO.
	 * @param Composer $composer Composer.
	 * @param string $projectPath Path to project directory.
	 * @param string $globalPath Path to global directory.
	 * @param string[] $excludedPackages Array of excluded packages.
	 */
	public function __construct(
		IOInterface $io,
		Composer $composer,
		string $projectPath,
		string $globalPath,
		array $excludedPackages
	) {
		parent::__construct($io, $composer, null, new Filesystem(new ProcessExecutor($io)));

		$this->projectPath = $projectPath;
		$this->globalPath = rtrim($globalPath, '/');
		$this->excludedPackages = $excludedPackages;
	}

	/**
	 * @inheritDoc
	 * @since 1.0.0
	 */
	public function supports($packageType): bool {
		return !in_array($packageType, ['composer-installer', 'composer-plugin', 'metapackage']);
	}

	/**
	 * @inheritDoc
	 * @since 1.0.0
	 */
	public function getInstallPath(PackageInterface $package): string {
		// Path repositories are always installed locally
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
	 * @since 1.0.0
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
	 * For supported packages, make sure they're installed globally. The locally installed packages are symlinks.
	 *
	 * @since 1.0.0
	 */
	public function download(PackageInterface $package, PackageInterface $prevPackage = null): ?PromiseInterface {
		if (!$this->supportsGlobalPath($package)) {
			return parent::download($package, $prevPackage);
		}

		// Make sure the package is installed globally
		$globalPath = $this->getGlobalPath($package);
		if (!Filesystem::isReadable($globalPath)) {
			// To make things even faster, download to system temp directory:
			// 1. We can't change the download path so temporary move the vendor directory for this project
			$config = $this->composer->getConfig();
			$vendor_dir = $config->get('vendor-dir');
			$config->merge([
				'config' => [
					'vendor-dir' => sys_get_temp_dir() . '/composer/' . md5($this->projectPath) . '/vendor',
				],
			]);

			// 2. Download & install package to global path
			SyncHelper::downloadAndInstallPackageSync(
				$this->composer->getLoop(),
				$this->composer->getDownloadManager()->getDownloader($package->getDistType()),
				$globalPath,
				$package
			);

			// 3. Swap vendor dir back to previous
			$config->merge(['config' => ['vendor-dir' => $vendor_dir]]);
		}

		// Change package to path repository and install locally
		$package->setDistType('path');
		$package->setDistUrl($globalPath);
		$package->setTransportOptions(['relative' => false]);

		return parent::download($package, $prevPackage);
	}

	/**
	 * @inheritDoc
	 *
	 * Remove symlinks to globally installed packages.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
	 * @return string The path.
	 */
	public function getBasePath(): string {
		return $this->globalPath;
	}

	/**
	 * Returns the global path for a package.
	 * @since 1.0.0
	 * @param PackageInterface $package Package.
	 * @return string The path.
	 */
	private function getGlobalPath(PackageInterface $package): ?string {
		return sprintf('%s/%s/%s', $this->getBasePath(), $package->getPrettyName(), $package->getPrettyVersion());
	}

	/**
	 * Checks if a package supports a global path.
	 * @since 1.0.0
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
