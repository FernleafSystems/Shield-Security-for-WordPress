<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData;

class SearchTextParser {

	public const REQUEST_ID = 'request_id';
	public const USER_EMAIL = 'user_email';
	public const USER_ID = 'user_id';
	public const USER_NAME = 'user_name';
	// i18n: descriptions used dynamically via __() in SearchHelp.
	// __( 'Filter results by IP address (full or partial match)', 'wp-simple-firewall' )
	// __( 'Filter results by request ID (exact match)', 'wp-simple-firewall' )
	// __( 'Filter results by WordPress user ID', 'wp-simple-firewall' )
	// __( 'Filter results by WordPress username (exact match)', 'wp-simple-firewall' )
	// __( 'Filter results by WordPress user email (exact match)', 'wp-simple-firewall' )
	private const FILTER_DEFINITIONS = [
		'ip'                   => [
			'sanitise'    => '#[^0-9a-f:.]#i',
			'description' => 'Filter results by IP address (full or partial match)',
			'example'     => 'ip:192.168.1.1',
		],
		self::REQUEST_ID => [
			'sanitise'    => '#[^a-zA-Z0-9]#',
			'description' => 'Filter results by request ID (exact match)',
			'example'     => self::REQUEST_ID.':abc123',
		],
		self::USER_ID => [
			'sanitise'    => '#[^0-9]#',
			'description' => 'Filter results by WordPress user ID',
			'example'     => self::USER_ID.':1',
		],
		self::USER_NAME => [
			'sanitise'    => '#[^a-zA-Z0-9_.@-]#',
			'description' => 'Filter results by WordPress username (exact match)',
			'example'     => self::USER_NAME.':admin',
		],
		self::USER_EMAIL => [
			'sanitise'    => '#[^a-zA-Z0-9+_.@-]#',
			'description' => 'Filter results by WordPress user email (exact match)',
			'example'     => self::USER_EMAIL.':user@example.com',
		],
	];

	public static function Parse( string $rawSearch ) :array {
		$result = [
			'remaining' => $rawSearch,
		];
		foreach ( self::FILTER_DEFINITIONS as $prefix => $def ) {
			$value = '';
			if ( \preg_match( '#\b'.$prefix.':\s*(\S+)#i', $result[ 'remaining' ], $matches ) ) {
				$candidate = \preg_replace( $def[ 'sanitise' ], '', $matches[ 1 ] );
				if ( !empty( $candidate ) ) {
					$value = $candidate;
					$result[ 'remaining' ] = \trim( \preg_replace( '#\b'.$prefix.':\s*\S+#i', '', $result[ 'remaining' ] ) );
				}
			}
			$result[ $prefix ] = $value;
		}
		return $result;
	}

	public static function GetFilterDefinitions() :array {
		return self::FILTER_DEFINITIONS;
	}

	public static function SanitiseForFilter( string $filterKey, string $value ) :string {
		$def = self::FILTER_DEFINITIONS[ $filterKey ] ?? null;
		return $def === null ? $value : \preg_replace( $def[ 'sanitise' ], '', $value );
	}
}
