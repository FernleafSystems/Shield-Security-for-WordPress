<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Strings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Scans\ScansProgress;

class ScansCheck extends ScansBase {

	const SLUG = 'scans_check';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		/** @var Strings $strings */
		$strings = $mod->getStrings();

		$statusChecker = ( new ScansStatus() )->setMod( $mod );
		$queueCon = $mod->getScanQueueController();
		$current = $statusChecker->current();
		$hasCurrent = !empty( $current );
		if ( $hasCurrent ) {
			$currentScan = $strings->getScanName( $current );
		}
		else {
			$currentScan = __( 'No scan running.', 'wp-simple-firewall' );
		}

		$running = $statusChecker->enqueued();

		if ( count( $running ) === 0 ) {
			$remainingScans = __( 'No scans remaining.', 'wp-simple-firewall' );
		}
		else {
			$remainingScans = sprintf( __( '%s scans remaining.', 'wp-simple-firewall' ),
				count( $running ) );
		}

		$this->response()->action_response_data = [
			'success' => true,
			'running' => $queueCon->getScansRunningStates(),
			'vars'    => [
				'progress_html' => $this->getCon()
										->getModule_Insights()
										->getActionRouter()
										->render( ScansProgress::SLUG, [
											'current_scan'    => $currentScan,
											'remaining_scans' => $remainingScans,
											'progress'        => 100*$queueCon->getScanJobProgress(),
										] ),
			]
		];
	}
}