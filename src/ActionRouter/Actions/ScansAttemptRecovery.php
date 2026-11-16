<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\{
	ExternalEntryGuard,
	RequestFields
};

/**
 * A progress check that first tries to get a stalled scan going again. The page follows the recovered scan from
 * then on, so it's among the scans this reports.
 */
class ScansAttemptRecovery extends ScansCheck {

	public const SLUG = 'scans_attempt_recovery';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		// Optional: anything but a genuine scan ID means no scan to recover.
		$scanID = RequestFields::PositiveIntFrom( $this->action_data[ 'scan_id' ] ?? null ) ?? 0;
		if ( $scanID > 0 ) {
			self::con()->comps->scans_queue->getQueueWatchdog()->recoverScanIfStale( $scanID );
		}
		parent::exec();
	}
}
