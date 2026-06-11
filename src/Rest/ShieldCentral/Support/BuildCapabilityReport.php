<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support;

use FernleafSystems\Wordpress\Services\Services;

class BuildCapabilityReport {

	public function build() :array {
		global $wpdb;

		return [
			'supports_openssl'      => \extension_loaded( 'openssl' ),
			'supports_hmac'         => true,
			'supports_url_fallback' => false,
			'supports_callback'     => false,
			'can_handshake'         => true,
			'can_wordpress_write'   => $this->canWordPressWrite(),
			'has_ext_pdo'           => \extension_loaded( 'pdo' ),
			'has_ext_mysqli'        => \extension_loaded( 'mysqli' ),
			'has_zip'               => \extension_loaded( 'zip' ) || \class_exists( '\ZipArchive' ),
			'php_version'           => \PHP_VERSION,
			'mysql_version'         => \is_object( $wpdb ) && \method_exists( $wpdb, 'db_version' )
				? (string)$wpdb->db_version()
				: '',
			'can_exec'              => \function_exists( 'exec' ),
			'can_unzip'             => \class_exists( '\ZipArchive' ),
			'can_set_time_limit'    => \function_exists( 'set_time_limit' )
									   && \stripos( (string)\ini_get( 'disable_functions' ), 'set_time_limit' ) === false,
			'is_force_ssl_admin'    => \function_exists( 'force_ssl_admin' ) && \force_ssl_admin(),
			'server_is_windows'     => \DIRECTORY_SEPARATOR === '\\',
		];
	}

	private function canWordPressWrite() :bool {
		try {
			return Services::WpFs()->isAccessibleDir( \WP_CONTENT_DIR );
		}
		catch ( \Exception $e ) {
			return \is_writable( \WP_CONTENT_DIR );
		}
	}
}
