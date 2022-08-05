<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base;

class Handler extends Base\Handler {

	const T_MANUAL_WHITE = 'MW';
	const T_MANUAL_BLACK = 'MB';
	const T_AUTO_BLACK = 'AB';
	const T_CROWDSEC = 'CS';

	public static function IsValidType( string $type ) :bool {
		return in_array( $type, [ self::T_CROWDSEC, self::T_MANUAL_BLACK, self::T_AUTO_BLACK, self::T_MANUAL_WHITE ] );
	}

	public static function GetTypeName( string $type ) :string {
		switch ( $type ) {
			case self::T_MANUAL_WHITE:
				$name = __( 'Bypass', 'wp-simple-firewall' );
				break;
			case self::T_MANUAL_BLACK:
				$name = __( 'Manual Block', 'wp-simple-firewall' );
				break;
			case self::T_AUTO_BLACK:
				$name = __( 'Auto Block', 'wp-simple-firewall' );
				break;
			case self::T_CROWDSEC:
				$name = 'CrowdSec';
				break;
			default:
				$name = __( 'Invalid', 'wp-simple-firewall' );
				break;
		}
		return $name;
	}
}