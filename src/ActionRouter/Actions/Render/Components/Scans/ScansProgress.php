<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansBase as ScanActionBase;

/**
 * @phpstan-import-type ActiveScanProgressRow from \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\Controller
 * @phpstan-import-type ScanModalState from ScanActionBase
 */
class ScansProgress extends BaseScans {

	public const SLUG = 'render_scans_progress';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/modal/progress.twig';

	protected function getRenderData() :array {
		/** @var array{modal_state:ScanModalState,current_scan:string,remaining_scans:string,progress:int,scan_rows:list<ActiveScanProgressRow>} $data */
		$data = $this->action_data;
		$modalState = $data[ 'modal_state' ];
		$isInitiating = $modalState === ScanActionBase::SCAN_MODAL_STATE_INITIATING;
		$isFailed = $modalState === ScanActionBase::SCAN_MODAL_STATE_FAILED;
		$isComplete = $modalState === ScanActionBase::SCAN_MODAL_STATE_COMPLETED;
		$isRunning = $isInitiating || $modalState === ScanActionBase::SCAN_MODAL_STATE_RUNNING;
		$currentScan = $data[ 'current_scan' ];
		$remainingScans = $data[ 'remaining_scans' ];
		$progress = $data[ 'progress' ];
		$scanRows = $this->buildScanRows( $data[ 'scan_rows' ] );

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
				'modal_title'        => __( 'Scan Progress', 'wp-simple-firewall' ),
				'patience_1'         => __( 'File scanning is an intensive operation and takes time.', 'wp-simple-firewall' ),
				'patience_2'         => __( 'We appreciate your patience.', 'wp-simple-firewall' ),
				'progress_label'     => __( 'Scan progress', 'wp-simple-firewall' ),
				'attempt_resume_now' => __( 'Attempt resume now', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'modal_state'     => $modalState,
				'heading'         => $heading,
				'remaining_scans' => $remainingScans,
				'progress'        => $progress,
				'announcement'    => $announcement,
				'is_busy'         => $isRunning,
				'show_progress'   => !$isFailed,
				'scan_rows'       => $scanRows,
				'modal_title_id'  => 'ShieldModalContainerLabel',
			],
		];
	}

	/**
	 * @param list<ActiveScanProgressRow> $rows
	 * @return list<array{id:int,name:string,scope_label:string,status_label:string,status_icon_class:string,status_class:string,progress_bar_class:string,can_attempt_recovery:bool,progress:int,aria_label:string}>
	 */
	private function buildScanRows( array $rows ) :array {
		$renderRows = [];
		foreach ( $rows as $row ) {
			$statusPresentation = $this->statusPresentation( $row[ 'display_status' ] );
			$renderRows[] = [
				'id'                   => $row[ 'id' ],
				'name'                 => $row[ 'name' ],
				'scope_label'          => $this->scopeLabel( $row[ 'scope_type' ], $row[ 'scope_key' ] ),
				'status_label'         => $statusPresentation[ 'label' ],
				'status_icon_class'    => $statusPresentation[ 'icon_class' ],
				'status_class'         => $statusPresentation[ 'status_class' ],
				'progress_bar_class'   => $statusPresentation[ 'progress_bar_class' ],
				'can_attempt_recovery' => $row[ 'can_attempt_recovery' ],
				'progress'             => $row[ 'progress' ],
				'aria_label'           => sprintf( __( '%s progress', 'wp-simple-firewall' ), $row[ 'name' ] ),
			];
		}
		return $renderRows;
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

	/**
	 * @param 'running'|'waiting'|'stalled' $status
	 * @return array{label:string,icon_class:string,status_class:string,progress_bar_class:string}
	 */
	private function statusPresentation( string $status ) :array {
		switch ( $status ) {
			case 'running':
				return [
					'label'              => __( 'running', 'wp-simple-firewall' ),
					'icon_class'         => 'bi bi-arrow-repeat',
					'status_class'       => 'shield-scan-progress__status--running',
					'progress_bar_class' => 'bg-success progress-bar-striped progress-bar-animated',
				];

			case 'waiting':
				return [
					'label'              => __( 'waiting', 'wp-simple-firewall' ),
					'icon_class'         => 'bi bi-hourglass-split',
					'status_class'       => 'shield-scan-progress__status--waiting',
					'progress_bar_class' => 'bg-secondary',
				];

			case 'stalled':
				return [
					'label'              => __( 'appears stalled', 'wp-simple-firewall' ),
					'icon_class'         => 'bi bi-exclamation-triangle-fill',
					'status_class'       => 'shield-scan-progress__status--stalled',
					'progress_bar_class' => 'bg-warning progress-bar-striped',
				];

			default:
				throw new \UnexpectedValueException( 'Unsupported scan progress status.' );
		}
	}

	protected function getRequiredDataKeys() :array {
		return [
			'modal_state',
			'current_scan',
			'remaining_scans',
			'progress',
			'scan_rows',
		];
	}
}
