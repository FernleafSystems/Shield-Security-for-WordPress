<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops;

class Handler extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler {

	public const T_AUTO_BYPASS = 'AW';
	public const T_MANUAL_BYPASS = 'MW';
	public const T_MANUAL_BLOCK = 'MB';
	public const T_AUTO_BLOCK = 'AB';
	public const T_CROWDSEC = 'CS';

	public static function IsValidType( string $type ) :bool {
		return \in_array( $type, [
			self::T_CROWDSEC,
			self::T_MANUAL_BLOCK,
			self::T_AUTO_BLOCK,
			self::T_MANUAL_BYPASS,
			self::T_AUTO_BYPASS
		] );
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