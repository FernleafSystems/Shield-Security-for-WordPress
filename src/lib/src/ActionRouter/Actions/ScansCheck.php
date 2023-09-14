<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansProgress;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Strings;

class ScansCheck extends ScansBase {

	public const SLUG = 'scans_check';

	protected function exec() {
		$mod = self::con()->getModule_HackGuard();
		/** @var Strings $strings */
		$strings = $mod->getStrings();

		$queueCon = $mod->getScanQueueController();
		$current = ( new ScansStatus() )->current();
		$currentScan = !empty( $current ) ? $strings->getScanName( $current ) : __( 'No scan running.', 'wp-simple-firewall' );

		$running = \count( ( new ScansStatus() )->enqueued() );

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