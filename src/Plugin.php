<?php

namespace Iwink\ComposerGlobalInstaller;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;

/**
 * Global installer plugin for Composer.
 * @since $ver$
 */
class Plugin implements PluginInterface, EventSubscriberInterface {
	/**
	 * Global installer.
	 * @since $ver$
	 * @var GlobalInstaller|null
	 */
	private ?GlobalInstaller $installer = null;

	/**
	 * @inheritDoc
	 * @since $ver$
	 */
	public function activate(Composer $composer, IOInterface $io): void {
		// Skip the entire plugin if the global-vendor-dir is set to "false".
		$options = $composer->getPackage()->getExtra()['global-installer'] ?? [];
		if (false === $options) {
			return;
		}

		// Validate options
		$options = (object) $options;
		$validator = new Validator();
		$validator->validate(
			$options, (object) ['$ref' => 'file://' . dirname(__DIR__) . '/res/schema.json'],
			Constraint::CHECK_MODE_APPLY_DEFAULTS
		);

		if (!$validator->isValid()) {
			$io->write('');
			$io->write('<error>Invalid options for iwink/global-installer-plugin, plugin disabled:</error>');
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
			$io->write(sprintf('<error>Path "%s" is not writable for iwink/global-installer-plugin, plugin disabled.</error>', $path));
			$io->write('');

			return;
		}

		$this->installer = new GlobalInstaller($io, $composer, $path, $options->exclude);
		$composer->getInstallationManager()->addInstaller($this->installer);
	}

	/**
	 * @inheritDoc
	 * @since $ver$
	 */
	public function deactivate(Composer $composer, IOInterface $io): void {
		if ($this->installer instanceof GlobalInstaller) {
			$composer->getInstallationManager()->removeInstaller($this->installer);
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
			ScriptEvents::PRE_AUTOLOAD_DUMP => 'setInstallPath',
			ScriptEvents::POST_AUTOLOAD_DUMP => 'resetInstallPath',
		];
	}

	/**
	 * Sets the install paths before generating the autoloader.
	 * @since $ver$
	 * @param Event $event The event.
	 */
	public function setInstallPath(Event $event): void {
		if($this->installer instanceof GlobalInstaller) {
			$this->installer->forceGlobalPath(true);
		}
	}

	/**
	 * Resets the install paths before generating the autoloader.
	 * @since $ver$
	 * @param Event $event The event.
	 */
	public function resetInstallPath(Event $event): void {
		if($this->installer instanceof GlobalInstaller) {
			$this->installer->forceGlobalPath(true);
		}
	}
}
