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
		$scanRows = $this->buildScanRows( \is_array( $this->action_data[ 'scan_rows' ] ?? null ) ? $this->action_data[ 'scan_rows' ] : [] );

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
				'has_scan_rows'   => !empty( $scanRows ),
				'scan_rows'       => $scanRows,
				'modal_title_id'  => 'ShieldModalContainerLabel',
			],
		];
	}

	/**
	 * @param list<array{id:int,scan:string,name:string,scope_type:string,scope_key:string,raw_status:string,display_status:string,is_current:bool,is_stale:bool,progress:int,total_items:int,unfinished:int}> $rows
	 * @return list<array{id:int,scan:string,name:string,scope_label:string,display_status:string,status_label:string,progress:int,aria_label:string}>
	 */
	private function buildScanRows( array $rows ) :array {
		return \array_map(
			fn( array $row ) :array => [
				'id'             => (int)$row[ 'id' ],
				'scan'           => $row[ 'scan' ],
				'name'           => $row[ 'name' ],
				'scope_label'    => $this->scopeLabel( $row[ 'scope_type' ], $row[ 'scope_key' ] ),
				'display_status' => $row[ 'display_status' ],
				'status_label'   => $this->statusLabel( $row[ 'display_status' ] ),
				'progress'       => (int)\max( 0, \min( 100, $row[ 'progress' ] ) ),
				'aria_label'     => sprintf( __( '%s progress', 'wp-simple-firewall' ), $row[ 'name' ] ),
			],
			$rows
		);
	}

	private function scopeLabel( string $scopeType, string $scopeKey ) :string {
		if ( $scopeType === 'full' || $scopeType === '' ) {
			return '';
		}

		if ( $scopeType === 'core' ) {
			return __( 'Core', 'wp-simple-firewall' );
		}

		if ( $scopeType === 'plugin' ) {
			return sprintf( __( 'Plugin: %s', 'wp-simple-firewall' ), $scopeKey );
		}

		if ( $scopeType === 'theme' ) {
			return sprintf( __( 'Theme: %s', 'wp-simple-firewall' ), $scopeKey );
		}

		return $scopeKey === '' ? $scopeType : sprintf( '%s: %s', $scopeType, $scopeKey );
	}

	private function statusLabel( string $status ) :string {
		switch ( $status ) {
			case 'running':
				return __( 'running', 'wp-simple-firewall' );

			case 'waiting':
				return __( 'waiting', 'wp-simple-firewall' );

			case 'stalled':
				return __( 'appears stalled', 'wp-simple-firewall' );

			default:
				return $status;
		}
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
