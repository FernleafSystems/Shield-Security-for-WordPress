<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansProgress;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;

class ScansCheck extends ScansBase {

	public const SLUG = 'scans_check';

	protected function exec() {
		$mod = self::con()->getModule_HackGuard();

		$current = ( new ScansStatus() )->current();
		$currentScan = __( 'No scan running.', 'wp-simple-firewall' );
		if ( !empty( $current ) ) {
			$currentScan = $mod->getScansCon()
							   ->getScanCon( $current )
							   ->getScanName();
		}

		$running = \count( ( new ScansStatus() )->enqueued() );
		$queueCon = $mod->getScanQueueController();

		$this->response()->action_response_data = [
			'success' => true,
			'running' => $queueCon->getScansRunningStates(),
			'vars'    => [
				'progress_html' => self::con()->action_router->render( ScansProgress::SLUG, [
					'current_scan'    => $currentScan,
					'remaining_scans' => $running === 0 ?
						__( 'No scans remaining.', 'wp-simple-firewall' )
						: sprintf( _n( '%s scan remaining.', '%s scans remaining.', $running ), $running ),
					'progress'        => 100*$queueCon->getScanJobProgress(),
				] ),
			]
		];
	}
}