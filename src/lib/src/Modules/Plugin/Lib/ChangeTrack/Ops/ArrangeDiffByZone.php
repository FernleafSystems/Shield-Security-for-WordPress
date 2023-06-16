<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Constants;

class ArrangeDiffByZone {

	public static function run( array $diff ) :array {
		$zoneDiffs = [];
		foreach ( Constants::ZONES as $zone ) {
			$zoneDiffs[ $zone::SLUG ] = [];
		}
		foreach ( [ 'added', 'removed', 'changed' ] as $type ) {
			foreach ( $diff[ $type ] as $zone => $changes ) {
				$zoneDiffs[ $zone ][ $type ] = $changes;
			}
		}
		return $zoneDiffs;
	}
}