<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Forms\FormParams;

class ScansStart extends ScansBase {

	public const SLUG = 'scans_start';

	protected function exec() {
		$con = self::con();
		$success = false;
		$reloadPage = false;
		$msg = __( 'No scans were selected', 'wp-simple-firewall' );

		$params = FormParams::Retrieve();
		if ( !empty( $params ) ) {
			$selectedScans = \array_intersect( \array_keys( $params ), $con->comps->scans->getScanSlugs() );
			$resetIgnore = (bool)( $params[ 'opt_clear_ignore' ] ?? false );
			if ( $con->comps->scans->startNewScans( $selectedScans, $resetIgnore ) ) {
				$success = true;
				$reloadPage = true;
				$msg = __( 'Scans started.', 'wp-simple-firewall' ).' '.__( 'Please wait, as this will take a few moments.', 'wp-simple-firewall' );
			}
		}

		$isScanRunning = $con->comps->scans_queue->hasRunningScans();

		$this->response()->action_response_data = [
			'success'       => $success,
			'scans_running' => $isScanRunning,
			'page_reload'   => $reloadPage && !$isScanRunning,
			'message'       => $msg,
		];
	}
}