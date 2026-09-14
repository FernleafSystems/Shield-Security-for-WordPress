<?php declare( strict_types=1 );

/**
 * Plugin Name: Shield Public 22.1.3 Cross-Site Runtime
 * Description: Temporary test-only legacy license and import-secret fixture.
 */

const SHIELD_CROSS_SITE_PUBLIC_2213_API_KEY = 'shield-cross-site-public-license-key';
const SHIELD_CROSS_SITE_PUBLIC_2213_IMPORT_SECRET = '0123456789abcdef0123456789abcdef01234567';
const SHIELD_CROSS_SITE_PUBLIC_2213_IMPORT_SECRET_EXPIRES_AT = 2147483647;

add_filter(
	'pre_http_request',
	static function( $preempt, array $parsedArgs, string $url ) {
		$parts = \wp_parse_url( $url );
		if ( !\is_array( $parts ) ) {
			return $preempt;
		}

		$host = \strtolower( (string)( $parts[ 'host' ] ?? '' ) );
		$path = (string)( $parts[ 'path' ] ?? '' );
		$method = \strtoupper( (string)( $parsedArgs[ 'method' ] ?? 'GET' ) );
		$body = null;

		if ( $method === 'POST'
			&& $host === 'net.getshieldsecurity.com'
			&& $path === '/wp-json/apto-snapi/v2/licenses/activate' ) {
			$body = [ 'error_code' => 0 ];
		}
		elseif ( $method === 'GET'
			&& $host === 'api.getshieldsecurity.com'
			&& $path === '/wp-json/apto-keyless/v2/licenses' ) {
			$body = [
				'error_code' => 0,
				'licenses' => [
					'shieldpro' => [
						'checksum' => '0123456789abcdef0123456789abcdef',
						'success' => true,
						'license' => 'valid',
						'expires' => 'lifetime',
						'lic_version' => 1,
						'capabilities' => [
							'wpcli_level_2',
							'import_export_level_1',
							'import_export_level_2',
						],
					],
				],
			];
		}

		return $body === null ? $preempt : [
			'headers' => [],
			'body' => \wp_json_encode( $body ),
			'response' => [
				'code' => 200,
				'message' => 'OK',
			],
			'cookies' => [],
			'filename' => null,
		];
	},
	10,
	3
);

add_filter(
	'option_icwp_wpsf_opts_all',
	static function( $options ) {
		if ( !\is_array( $options ) ) {
			return $options;
		}
		foreach ( [ 'free', 'pro' ] as $type ) {
			if ( !\is_array( $options[ 'values' ][ $type ] ?? null ) ) {
				continue;
			}
			$options[ 'values' ][ $type ][ 'importexport_secretkey' ] = SHIELD_CROSS_SITE_PUBLIC_2213_IMPORT_SECRET;
			$options[ 'values' ][ $type ][ 'importexport_secretkey_expires_at' ] = SHIELD_CROSS_SITE_PUBLIC_2213_IMPORT_SECRET_EXPIRES_AT;
		}
		return $options;
	},
	10
);

add_filter(
	'pre_update_option_icwp_wpsf_opts_all',
	static function( $options ) {
		if ( !\is_array( $options ) ) {
			return $options;
		}
		foreach ( [ 'free', 'pro' ] as $type ) {
			if ( !\is_array( $options[ 'values' ][ $type ] ?? null ) ) {
				continue;
			}
			unset(
				$options[ 'values' ][ $type ][ 'importexport_secretkey' ],
				$options[ 'values' ][ $type ][ 'importexport_secretkey_expires_at' ]
			);
		}
		return $options;
	},
	10
);
