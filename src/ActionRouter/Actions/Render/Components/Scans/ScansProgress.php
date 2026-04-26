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
		$isRunning = $isInitiating || $modalState === ScanActionBase::SCAN_MODAL_STATE_RUNNING;
		$currentScan = (string)$this->action_data[ 'current_scan' ];
		$remainingScans = (string)$this->action_data[ 'remaining_scans' ];
		$progress = (int)$this->action_data[ 'progress' ];

		$failedText = __( 'Scan failed.', 'wp-simple-firewall' );
		$completeText = __( 'Scans completed.', 'wp-simple-firewall' );
		$initiatingText = __( 'Preparing scans.', 'wp-simple-firewall' );
		$currentScanText = \sprintf( __( 'Current Scan: %s', 'wp-simple-firewall' ), $currentScan );
		$statusText = $isFailed ? $failedText
			: ( $isComplete ? $completeText
				: ( $isInitiating ? $initiatingText : $currentScanText ) );
		$announcement = $isFailed
			? \trim( $statusText.' '.$remainingScans )
			: \sprintf( '%s %d%%', $statusText, $progress );
		$heading = $isComplete
			? $completeText.' '.__( 'Reloading page', 'wp-simple-firewall' ).'...'
			: $statusText;

		return [
			'strings' => [
				'modal_title'    => __( 'Scan Progress', 'wp-simple-firewall' ),
				'patience_1'     => __( 'File scanning is an intensive operation and takes time.', 'wp-simple-firewall' ),
				'patience_2'     => __( 'We appreciate your patience.', 'wp-simple-firewall' ),
				'progress_label' => __( 'Scan progress', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'modal_state'     => $modalState,
				'heading'         => $heading,
				'remaining_scans' => $remainingScans,
				'progress'        => $progress,
				'announcement'    => $announcement,
				'is_busy'         => $isRunning,
				'show_progress'   => !$isFailed,
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
