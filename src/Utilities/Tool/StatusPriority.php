<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool;

class StatusPriority {

	private const RANKS = [
		'info'     => 0,
		'good'     => 1,
		'warning'  => 2,
		'critical' => 3,
	];

	public static function highest( array $statuses, string $default = 'info' ) :string {
		$current = \array_key_exists( $default, self::RANKS ) ? $default : 'info';
		foreach ( $statuses as $status ) {
			$status = (string)$status;
			if ( ( self::RANKS[ $status ] ?? -1 ) > self::RANKS[ $current ] ) {
				$current = $status;
			}
		}
		return $current;
	}
}
