<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots;

class Hasher {

	public static function Snap( array $snap ) :string {
		\ksort( $snap );
		return \hash( 'sha1', \serialize( $snap ) );
	}

	public static function Item( string $item, int $substrLength = 16 ) :string {
		$hash = \hash( 'sha256', $item );
		return empty( $substrLength ) ? $hash : \substr( $hash, 0, $substrLength );
	}
}