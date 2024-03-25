<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Mfa\Ops\Record;
use FernleafSystems\Wordpress\Services\Services;

class MfaRecordsForDisplay {

	public function run( array $records ) :array {

		/**
		 * Order by most recently used first, then most recently registered.
		 */
		\usort( $records, function ( Record $a, Record $b ) {
			$atA = $a->used_at;
			$atB = $b->used_at;
			if ( $atA === $atB ) {
				$atA = $a->created_at;
				$atB = $b->created_at;
				$ret = $atA == $atB ? 0 : ( $atA > $atB ? -1 : 1 );
			}
			else {
				$ret = $atA > $atB ? -1 : 1;
			}
			return $ret;
		} );

		return \array_map(
			function ( Record $record ) {
				return [
					'id'      => $record->unique_id,
					'label'   => $record->label,
					'used_at' => sprintf(
						'%s: %s', __( 'Used', 'wp-simple-firewall' ),
						$record->used_at === 0 ? __( 'Never' ) :
							Services::Request()
									->carbon( true )
									->setTimestamp( $record->used_at )
									->diffForHumans()
					),
					'reg_at'  => sprintf(
						'%s: %s', __( 'Registered', 'wp-simple-firewall' ),
						$record->created_at === 0 ? __( 'Unknown' ) :
							Services::Request()
									->carbon( true )
									->setTimestamp( $record->created_at )
									->diffForHumans()
					)
				];
			},
			$records
		);
	}
}