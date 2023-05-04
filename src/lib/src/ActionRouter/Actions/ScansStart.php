<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;

class ScansStart extends ScansBase {

	public const SLUG = 'scans_start';

	protected function exec() {
		$mod = $this->con()->getModule_HackGuard();
		$success = false;
		$reloadPage = false;
		$msg = __( 'No scans were selected', 'wp-simple-firewall' );
		$params = FormParams::Retrieve();

		if ( !empty( $params ) ) {
			$scansCon = $mod->getScansCon();
			$uiTrack = $mod->getUiTrack();
			$uiTrack->selected_scans = array_intersect( array_keys( $params ), $scansCon->getScanSlugs() );
			$mod->setUiTrack( $uiTrack );

			$resetIgnore = (bool)( $params[ 'opt_clear_ignore' ] ?? false );
			if ( $scansCon->startNewScans( $uiTrack->selected_scans, $resetIgnore ) ) {
				$success = true;
				$reloadPage = true;
				$msg = __( 'Scans started.', 'wp-simple-firewall' ).' '.__( 'Please wait, as this will take a few moments.', 'wp-simple-firewall' );
			}
		}

		$isScanRunning = $mod->getScanQueueController()->hasRunningScans();

		$this->response()->action_response_data = [
			'success'       => $success,
			'scans_running' => $isScanRunning,
			'page_reload'   => $reloadPage && !$isScanRunning,
			'message'       => $msg,
		];
	}
}