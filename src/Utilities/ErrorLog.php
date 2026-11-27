<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities;

/**
 * Shield's one route to the PHP error log. The only direct call left is plugin_init.php's last-resort report
 * that the plugin failed to load, because a failed upgrade can leave this class missing.
 */
class ErrorLog {

	/**
	 * Records a failure that a site owner or support needs to be able to find.
	 */
	public static function write( string $message ) :void {
		\error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Shield's single route to the PHP error log, for failures that need recording on live sites.
	}

	/**
	 * A readable rendering of any value for a log or exception message, including values JSON can't encode.
	 * @param mixed $value
	 */
	public static function describe( $value ) :string {
		return \var_export( $value, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Renders a value as text for a log or exception message; nothing is printed.
	}
}
