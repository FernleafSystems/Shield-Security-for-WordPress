<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base;

class Handler extends Base\Handler {

	const T_MANUAL_BYPASS = 'MW';
	const T_MANUAL_BLOCK = 'MB';
	const T_AUTO_BLOCK = 'AB';
	const T_CROWDSEC = 'CS';

	public static function IsValidType( string $type ) :bool {
		return in_array( $type, [ self::T_CROWDSEC, self::T_MANUAL_BLOCK, self::T_AUTO_BLOCK, self::T_MANUAL_BYPASS ] );
	}

	public static function GetTypeName( string $type ) :string {
		switch ( $type ) {
			case self::T_MANUAL_BYPASS:
				$name = __( 'Bypass', 'wp-simple-firewall' );
				break;
			case self::T_MANUAL_BLOCK:
				$name = __( 'Manual Block', 'wp-simple-firewall' );
				break;
			case self::T_AUTO_BLOCK:
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