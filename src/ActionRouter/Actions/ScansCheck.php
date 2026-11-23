<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScanProgressBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;

class ScansCheck extends BaseAction {

	public const SLUG = 'scans_check';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$this->response()
			 ->setPayload( ( new ScanProgressBuilder() )->forScans( $this->trackedScanIDs() ) )
			 ->setPayloadSuccess( true );
	}

	/**
	 * The scans the page follows, those it started and any it recovered, so a failure among them is reported.
	 * @return list<int>
	 */
	private function trackedScanIDs() :array {
		return \array_values( \array_unique( $this->fields()->positiveIntList( 'scan_ids', [] ) ) );
	}
}
