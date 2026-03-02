<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool;

class StatusPriority {

	private const RANKS = [
		'info'     => 0,
		'good'     => 1,
		'warning'  => 2,
		'critical' => 3,
	];

	public static function normalize( string $status, string $default = 'info' ) :string {
		$status = \strtolower( \trim( $status ) );
		if ( \array_key_exists( $status, self::RANKS ) ) {
			return $status;
		}
		$default = \strtolower( \trim( $default ) );
		return \array_key_exists( $default, self::RANKS ) ? $default : 'info';
	}

	public static function rank( string $status, int $unknownRank = -1 ) :int {
		$status = \strtolower( \trim( $status ) );
		return self::RANKS[ $status ] ?? $unknownRank;
	}

	public static function highest( array $statuses, string $default = 'info' ) :string {
		$current = self::normalize( $default, 'info' );
		foreach ( $statuses as $status ) {
			$status = \strtolower( \trim( (string)$status ) );
			if ( self::rank( $status ) > self::rank( $current ) ) {
				$current = $status;
			}
		}
		return $current;
	}
}
