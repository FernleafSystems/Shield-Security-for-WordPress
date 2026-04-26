<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansProgress;

abstract class ScansBase extends BaseAction {

	public const SCAN_MODAL_STATE_INITIATING = 'initiating';
	public const SCAN_MODAL_STATE_RUNNING = 'running';
	public const SCAN_MODAL_STATE_COMPLETED = 'completed';
	public const SCAN_MODAL_STATE_FAILED = 'failed';

	protected function renderScanModalPayload( string $modalState, array $renderData = [] ) :array {
		$modalState = $this->normaliseScanModalState( $modalState );
		$progress = (int)\max( 0, \min( 100, \round( (float)( $renderData[ 'progress' ] ?? 0 ) ) ) );

		$renderData = \array_merge( $renderData, [
			'modal_state' => $modalState,
			'progress'    => $progress,
		] );

		return [
			'modal_state' => $modalState,
			'modal_html'  => self::con()->action_router->render( ScansProgress::class, $renderData ),
		];
	}

	private function normaliseScanModalState( string $modalState ) :string {
		return \in_array( $modalState, [
			self::SCAN_MODAL_STATE_INITIATING,
			self::SCAN_MODAL_STATE_RUNNING,
			self::SCAN_MODAL_STATE_COMPLETED,
			self::SCAN_MODAL_STATE_FAILED,
		], true ) ? $modalState : self::SCAN_MODAL_STATE_RUNNING;
	}
}
