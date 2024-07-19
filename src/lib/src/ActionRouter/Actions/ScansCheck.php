<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansProgress;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;

class ScansCheck extends ScansBase {

	public const SLUG = 'scans_check';

	protected function exec() {
		$con = self::con();

		$current = ( new ScansStatus() )->current();
		$currentScan = __( 'No scan running.', 'wp-simple-firewall' );
		if ( !empty( $current ) ) {
			$currentScan = $con->comps->scans->getScanCon( $current )->getScanName();
		}

		$running = \count( ( new ScansStatus() )->enqueued() );

		$this->response()->action_response_data = [
			'success' => true,
			'running' => $con->comps->scans_queue->getScansRunningStates(),
			'vars'    => [
				'progress_html' => self::con()->action_router->render( ScansProgress::class, [
					'current_scan'    => $currentScan,
					'remaining_scans' => $running === 0 ?
						__( 'No scans remaining.', 'wp-simple-firewall' )
						: sprintf( _n( '%s scan remaining.', '%s scans remaining.', $running ), $running ),
					'progress'        => 100*$con->comps->scans_queue->getScanJobProgress(),
				] ),
			]
		];
	}
}