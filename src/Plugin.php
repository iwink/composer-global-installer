<?php

namespace Iwink\ComposerGlobalInstaller;

use Composer\Autoload\AutoloadGenerator;
use Composer\Composer;
use Composer\Console\Application;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventDispatcher;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * Global installer plugin for Composer.
 * @since $ver$
 */
class Plugin implements PluginInterface, EventSubscriberInterface {
	/**
	 * The name of the package.
	 * @since $ver$
	 * @var string
	 */
	public const PACKAGE_NAME = 'iwink/composer-global-installer';

	/**
	 * Global installer.
	 * @since $ver$
	 * @var GlobalInstaller|null
	 */
	private ?GlobalInstaller $installer = null;

	/**
	 * Composer's original autoload generator.
	 * @since $ver$
	 * @var AutoloadGenerator|null
	 */
	private ?AutoloadGenerator $autoloadGenerator = null;

	/**
	 * Array containing the autoload generator options.
	 * @since $ver$
	 * @var array
	 */
	private array $autoloadGeneratorOptions = [];

	/**
	 * @inheritDoc
	 * @since $ver$
	 */
	public function activate(Composer $composer, IOInterface $io): void {
		// Try to load global options
		$globalOptions = [];
		$globalComposer = $composer->getPluginManager()->getGlobalComposer();
		if (null !== $globalComposer) {
			$globalOptions = $globalComposer->getPackage()->getExtra()['global-installer'] ?? [];
		}

		// Load options, skip the entire plugin if options are set to "false"
		$options = $composer->getPackage()->getExtra()['global-installer'] ?? $globalOptions;
		if (false === $options) {
			$io->notice(sprintf('<info>The "%s" plugin is disabled</info>', self::PACKAGE_NAME));

			return;
		}

		// Merge & validate options
		$options = (object) array_merge_recursive(!is_array($globalOptions) ? [] : $globalOptions, $options);
		$validator = new Validator();
		$validator->validate(
			$options, (object) ['$ref' => 'file://' . dirname(__DIR__) . '/res/schema.json'],
			Constraint::CHECK_MODE_APPLY_DEFAULTS
		);

		if (!$validator->isValid()) {
			$io->write('');
			$io->write(sprintf('<error>Invalid options for "%s", plugin disabled:</error>', self::PACKAGE_NAME));
			foreach ($validator->getErrors() as $error) {
				$io->write(sprintf(' * [%s] %s.', $error['property'], $error['message']));
			}

			$io->write('');

			return;
		}

		// Check if path is writable
		$path = $options->path;
		if (!Filesystem::isReadable($path) || !is_writable($path)) {
			$io->write('');
			$io->write(sprintf('<error>Path "%s" is not writable for "%s", plugin disabled.</error>', $path, self::PACKAGE_NAME));
			$io->write('');

			return;
		}

		// Register symlink installer
		$this->installer = new GlobalInstaller($io, $composer, $path, $options->exclude);
		$composer->getInstallationManager()->addInstaller($this->installer);

		// Register symlink resolving autoloader
		$this->autoloadGenerator = $composer->getAutoloadGenerator();
		$composer->setAutoloadGenerator(new GlobalAutoloadGenerator($composer->getEventDispatcher()));

		// When this package is required, installed or updated, Composer uses its original autoload generator so we need
		// to retrieve the input from the CLI to configure our custom autoload generator
		foreach (debug_backtrace() as $trace) {
			if (!isset($trace['object'], $trace['args'][0])) {
				continue;
			}

			if (!$trace['object'] instanceof Application || !$trace['args'][0] instanceof ArgvInput) {
				continue;
			}

			$input = $trace['args'][0];
			$app = $trace['object'];

			try {
				$command = $input->getFirstArgument();
				$command = $command ? $app->find($command) : null;

				// Only store for require, install & update commands
				if (null === $command || !in_array($command->getName(), ['require', 'install', 'update'])) {
					continue;
				}

				try {
					// The input consists of raw values, bind the definition so we can use the methods
					$command->mergeApplicationDefinition(); // Copy Symfony's internal logic
					$input->bind($command->getDefinition());
				} catch (ExceptionInterface $e) {
					// ignore invalid options/arguments for now, these are captured by Composer internally
				}

				// @see InstallCommand::execute()
				$this->autoloadGeneratorOptions = [
					'optimize-autoloader' => $input->getOption('optimize-autoloader'),
					'classmap-authoritative' => $input->getOption('classmap-authoritative'),
					'apcu-autoloader-prefix' => $input->getOption('apcu-autoloader-prefix'),
					'apcu-autoloader' => $input->getOption('apcu-autoloader-prefix') !== null || $input->getOption('apcu-autoloader'),
					'ignore-platform-reqs' => $input->getOption('ignore-platform-reqs') ?: ($input->getOption('ignore-platform-req') ?: false),
				];
			} catch (\InvalidArgumentException $e) {
				continue;
			}
		}
	}

	/**
	 * @inheritDoc
	 * @since $ver$
	 */
	public function deactivate(Composer $composer, IOInterface $io): void {
		if ($this->installer instanceof GlobalInstaller) {
			$composer->getInstallationManager()->removeInstaller($this->installer);
		}

		if ($this->autoloadGenerator instanceof AutoloadGenerator) {
			$composer->setAutoloadGenerator($this->autoloadGenerator);
			$this->autoloadGenerator = null;
		}
	}

	/**
	 * @inheritDoc
	 * @since $ver$
	 */
	public function uninstall(Composer $composer, IOInterface $io): void {
	}

	/**
	 * @inheritDoc
	 * @since $ver$
	 */
	public static function getSubscribedEvents(): array {
		return [
			PackageEvents::POST_PACKAGE_INSTALL => 'generateAutoloader',
			PackageEvents::POST_PACKAGE_UPDATE => 'generateAutoloader',
		];
	}

	/**
	 * Triggers an additional autoload generation when required.
	 * @since $ver$
	 * @param PackageEvent $event The event.
	 */
	public function generateAutoloader(PackageEvent $event): void {
		$operation = $event->getOperation();
		if (!$operation instanceof InstallOperation && !$operation instanceof UpdateOperation) {
			return;
		}

		// Only trigger additional generation if it's this package that was required, installed or updated
		$package = $operation instanceof InstallOperation ? $operation->getPackage() : $operation->getTargetPackage();
		if (self::PACKAGE_NAME !== $package->getName() || empty($this->autoloadGeneratorOptions)) {
			return;
		}

		// Register an anonymous listener to generate the autoloader
		$event->getComposer()
			->getEventDispatcher()
			->addListener(
				ScriptEvents::POST_AUTOLOAD_DUMP,
				function (Event $event): void {
					$composer = $event->getComposer();
					$io = new NullIO();

					// Create a `null` event dispatcher so we don't trigger additional events
					$eventDispatcher = new class($composer, $io) extends EventDispatcher {
						protected function doDispatch(\Composer\EventDispatcher\Event $event): int {
							return 0;
						}
					};
					$autoloadGenerator = new GlobalAutoloadGenerator($eventDispatcher, $io);

					// Rebuild options by combining the CLI options with the config options
					$config = $composer->getConfig();
					$optimize = $this->autoloadGeneratorOptions['optimize-autoloader'] || $config->get('optimize-autoloader');
					$authoritative = $this->autoloadGeneratorOptions['classmap-authoritative'] || $config->get('classmap-authoritative');
					$apcuPrefix = $this->autoloadGeneratorOptions['apcu-autoloader-prefix'];
					$apcu = $this->autoloadGeneratorOptions['apcu-autoloader'] || $config->get('apcu-autoloader');
					$ignorePlatformReqs = $this->autoloadGeneratorOptions['ignore-platform-reqs'];

					// Configure autoload generator
					$autoloadGenerator->setClassMapAuthoritative($authoritative);
					$autoloadGenerator->setApcu($apcu, $apcuPrefix);
					$autoloadGenerator->setRunScripts(false);
					$autoloadGenerator->setIgnorePlatformRequirements($ignorePlatformReqs);

					// Dump autoloader
					$autoloadGenerator->dump(
						$config,
						$composer->getRepositoryManager()->getLocalRepository(),
						$composer->getPackage(),
						$composer->getInstallationManager(),
						'composer',
						$optimize
					);
				},
				255
			);
	}
}
