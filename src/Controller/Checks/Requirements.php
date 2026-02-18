<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Checks;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Supported if:
 * - the mysql version is at least the minimum version
 * - OR: it's mariaDB, and it doesn't match the pattern: 5.5.xx-MariaDB
 * - OR: we can find the function 'INET6_ATON'
 */
class Requirements {

	use PluginControllerConsumer;

	public function isMysqlVersionSupported( string $versionToSupport ) :bool {
		$mysqlInfo = Services::WpDb()->getMysqlServerInfo();

		// WordPress Playground uses a SQLite-backed database compatibility layer.
		// MySQL utility commands like "HELP ..." are unsupported there.
		if ( $this->isSqliteBackend( $mysqlInfo ) ) {
			$supported = true;
		}
		else {
			$supported = empty( $versionToSupport )
						 || empty( $mysqlInfo )
						 || \version_compare( \preg_replace( '/[^\d.].*/', '', $mysqlInfo ), $versionToSupport, '>=' )
						 || ( \stripos( $mysqlInfo, 'MariaDB' ) !== false && !\preg_match( '#5.5.\d+-MariaDB#i', $mysqlInfo ) );
			if ( !$supported ) {
				try {
					$miscFunctions = Services::WpDb()->selectCustom( "HELP miscellaneous_functions" );
					foreach ( \is_array( $miscFunctions ) ? $miscFunctions : [] as $fn ) {
						if ( \is_array( $fn ) && \strtoupper( $fn[ 'name' ] ?? '' ) === 'INET6_ATON' ) {
							$supported = true;
							break;
						}
					}
				}
				catch ( \Throwable $e ) {
					// Leave unsupported=false if capability probing fails.
				}
			}
		}
		return $supported;
	}

	private function isSqliteBackend( string $mysqlInfo ) :bool {
		global $wpdb;
		return ( \stripos( $mysqlInfo, 'sqlite' ) !== false )
			   || ( \is_object( $wpdb ) && \stripos( \get_class( $wpdb ), 'sqlite' ) !== false );
	}
}
