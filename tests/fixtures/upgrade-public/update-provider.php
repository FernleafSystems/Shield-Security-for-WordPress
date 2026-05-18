<?php declare( strict_types=1 );

/**
 * Plugin Name: Shield Upgrade Test Update Provider
 * Description: Test-only update metadata provider for the public-to-current Shield upgrade lane.
 */

if ( !\function_exists( 'shield_upgrade_test_update_config_path' ) ) {
	function shield_upgrade_test_update_config_path() :string {
		return \defined( 'WP_CONTENT_DIR' )
			? WP_CONTENT_DIR.'/shield-upgrade-test/update.json'
			: '';
	}
}

if ( !\function_exists( 'shield_upgrade_test_read_update_config' ) ) {
	/**
	 * @return array<string,mixed>
	 */
	function shield_upgrade_test_read_update_config() :array {
		$path = shield_upgrade_test_update_config_path();
		if ( $path === '' || !\is_file( $path ) ) {
			return [];
		}

		$decoded = \json_decode( (string)\file_get_contents( $path ), true );
		return \is_array( $decoded ) ? $decoded : [];
	}
}

if ( !\function_exists( 'shield_upgrade_test_build_update_offer' ) ) {
	/**
	 * @param array<string,mixed> $config
	 */
	function shield_upgrade_test_build_update_offer( array $config ) :?\stdClass {
		foreach ( [ 'plugin', 'slug', 'new_version', 'package', 'url' ] as $key ) {
			if ( !\is_string( $config[ $key ] ?? null ) || \trim( (string)$config[ $key ] ) === '' ) {
				return null;
			}
		}

		return (object)[
			'id'          => (string)( $config[ 'id' ] ?? $config[ 'slug' ] ),
			'slug'        => (string)$config[ 'slug' ],
			'plugin'      => (string)$config[ 'plugin' ],
			'new_version' => (string)$config[ 'new_version' ],
			'url'         => (string)$config[ 'url' ],
			'package'     => (string)$config[ 'package' ],
		];
	}
}

if ( !\function_exists( 'shield_upgrade_test_apply_update_metadata' ) ) {
	function shield_upgrade_test_apply_update_metadata( $transient, ?array $config = null ) {
		$offer = shield_upgrade_test_build_update_offer( $config ?? shield_upgrade_test_read_update_config() );
		if ( $offer === null ) {
			return $transient;
		}

		if ( !\is_object( $transient ) ) {
			$transient = new \stdClass();
		}
		if ( !isset( $transient->response ) || !\is_array( $transient->response ) ) {
			$transient->response = [];
		}
		if ( !isset( $transient->checked ) || !\is_array( $transient->checked ) ) {
			$transient->checked = [];
		}
		if ( isset( $transient->no_update ) && \is_array( $transient->no_update ) ) {
			unset( $transient->no_update[ $offer->plugin ] );
		}

		$transient->last_checked = \time();
		$transient->response[ $offer->plugin ] = $offer;

		return $transient;
	}
}

if ( !\function_exists( 'shield_upgrade_test_plugins_api' ) ) {
	function shield_upgrade_test_plugins_api( $result, string $action, $args ) {
		$offer = shield_upgrade_test_build_update_offer( shield_upgrade_test_read_update_config() );
		if ( $offer === null
			 || $action !== 'plugin_information'
			 || !\is_object( $args )
			 || (string)( $args->slug ?? '' ) !== $offer->slug ) {
			return $result;
		}

		return (object)[
			'name'          => 'Shield Security',
			'slug'          => $offer->slug,
			'version'       => $offer->new_version,
			'download_link' => $offer->package,
			'sections'      => [
				'description' => 'Shield upgrade test package.',
			],
		];
	}
}

if ( !\function_exists( 'shield_upgrade_test_allow_package_host' ) ) {
	/**
	 * @param array<string,mixed>|null $config
	 */
	function shield_upgrade_test_allow_package_host( bool $isExternal, string $host, string $url, ?array $config = null ) :bool {
		$config = $config ?? shield_upgrade_test_read_update_config();
		$packageHost = \is_string( $config[ 'package' ] ?? null ) ? \parse_url( $config[ 'package' ], PHP_URL_HOST ) : null;

		return \is_string( $packageHost ) && $packageHost !== '' && $host === $packageHost ? true : $isExternal;
	}
}

if ( \function_exists( 'add_filter' ) ) {
	\add_filter( 'site_transient_update_plugins', 'shield_upgrade_test_apply_update_metadata', 99 );
	\add_filter( 'pre_set_site_transient_update_plugins', 'shield_upgrade_test_apply_update_metadata', 99 );
	\add_filter( 'plugins_api', 'shield_upgrade_test_plugins_api', 99, 3 );
	\add_filter( 'http_request_host_is_external', 'shield_upgrade_test_allow_package_host', 99, 3 );
}
