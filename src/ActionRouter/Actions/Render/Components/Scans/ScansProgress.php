<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansBase as ScanActionBase;

class ScansProgress extends BaseScans {

	public const SLUG = 'render_scans_progress';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/modal/progress.twig';

	protected function getRenderData() :array {
		$modalState = (string)$this->action_data[ 'modal_state' ];
		$isInitiating = $modalState === ScanActionBase::SCAN_MODAL_STATE_INITIATING;
		$isFailed = $modalState === ScanActionBase::SCAN_MODAL_STATE_FAILED;
		$isComplete = $modalState === ScanActionBase::SCAN_MODAL_STATE_COMPLETED;

		return [
			'strings' => [
				'modal_title'  => __( 'Scan Progress', 'wp-simple-firewall' ),
				'current_scan' => __( 'Current Scan', 'wp-simple-firewall' ),
				'initiating'   => __( 'Preparing scans.', 'wp-simple-firewall' ),
				'patience_1'   => __( 'File scanning is an intensive operation and takes time.', 'wp-simple-firewall' ),
				'patience_2'   => __( 'We appreciate your patience.', 'wp-simple-firewall' ),
				'completed'    => __( 'Scans completed.', 'wp-simple-firewall' ).' '.__( 'Reloading page', 'wp-simple-firewall' ).'...',
				'failed'       => __( 'Scan failed.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'modal_state'     => $modalState,
				'current_scan'    => $this->action_data[ 'current_scan' ],
				'remaining_scans' => $this->action_data[ 'remaining_scans' ],
				'progress'        => $this->action_data[ 'progress' ],
				'is_initiating'   => $isInitiating,
				'is_running'      => $isInitiating || $modalState === ScanActionBase::SCAN_MODAL_STATE_RUNNING,
				'is_complete'     => $isComplete,
				'is_failed'       => $isFailed,
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'modal_state',
			'current_scan',
			'remaining_scans',
			'progress',
		];
	}
}
