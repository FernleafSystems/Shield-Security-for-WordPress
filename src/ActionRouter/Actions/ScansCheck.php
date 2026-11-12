<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;

class ScansCheck extends ScansBase {

	public const SLUG = 'scans_check';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$this->response()
			 ->setPayload( $this->buildScanProgressPayload( $this->startedScanIdsFromActionData() ) )
			 ->setPayloadSuccess( true );
	}
}
