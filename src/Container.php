<?php
/**
 * Container class file.
 */

declare( strict_types = 1 );

namespace Automattic\WooCommerce\StockNotificationsMigrator;

use Automattic\WooCommerce\Internal\DataStores\StockNotifications\StockNotificationsDataStore;
use Automattic\WooCommerce\StockNotificationsMigrator\Report\Reporter;
use Automattic\WooCommerce\StockNotificationsMigrator\Runners\Cli;
use Automattic\WooCommerce\StockNotificationsMigrator\Runners\MigrationBatchProcessor;
use Automattic\WooCommerce\StockNotificationsMigrator\Runners\ToolsRegistrar;
use Automattic\WooCommerce\StockNotificationsMigrator\Writers\Writer;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's service locator.
 *
 * WooCommerce's own container (`wc_get_container()`) only resolves classes inside Core's
 * namespaces, so the migration cannot be wired through it from out here. This keeps the same
 * shape the code already expected: one shared instance per class, created on first ask, with
 * the `init()` dependency injection Core's container would have performed.
 *
 * Core classes are still resolved from `wc_get_container()`; only the plugin's own classes
 * live here.
 */
final class Container {

	/**
	 * Instances created so far, keyed by class name.
	 *
	 * @var array<string, object>
	 */
	private static array $instances = array();

	/**
	 * Get the shared instance of one of the plugin's classes.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return object
	 */
	public static function get( string $class_name ): object {
		return self::$instances[ $class_name ] ??= self::make( $class_name );
	}

	/**
	 * Replace an instance. Tests only — production code never calls this.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @param object $instance   Instance to hand out from now on.
	 * @return void
	 */
	public static function set( string $class_name, object $instance ): void {
		self::$instances[ $class_name ] = $instance;
	}

	/**
	 * Forget every instance. Tests only.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instances = array();
	}

	/**
	 * Build one instance, injecting what it declares.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return object
	 */
	private static function make( string $class_name ): object {
		switch ( $class_name ) {
			case Requirements::class:
				$requirements = new Requirements();
				$requirements->init( wc_get_container()->get( StockNotificationsDataStore::class ) );

				return $requirements;

			case MigrationBatchProcessor::class:
				$processor = new MigrationBatchProcessor();
				$processor->init( self::get( Requirements::class ), self::get( Writer::class ) );

				return $processor;

			case MigrationController::class:
			case MigrationState::class:
			case Reporter::class:
			case ToolsRegistrar::class:
			case Writer::class:
			case Cli::class:
				return new $class_name();

			default:
				throw new \InvalidArgumentException( esc_html( "{$class_name} is not a service of the Back In Stock Notifications migrator." ) );
		}
	}
}
