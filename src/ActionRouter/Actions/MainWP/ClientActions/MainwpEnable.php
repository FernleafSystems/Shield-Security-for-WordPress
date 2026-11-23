<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ClientActions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\MainwpBase;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;

/**
 * Run on a client site by an authenticated MainWP dashboard to switch on the MainWP integration.
 */
class MainwpEnable extends MainwpBase {

	public const SLUG = 'mwp_client_enable';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_MAINWP => ExternalEntryGuard::PROOF_NONE,
		];
	}

	protected function exec() {
		self::con()->opts->optSet( 'enable_mainwp', 'Y' );
		$this->response()->setPayload()->setPayloadSuccess( true );
	}
}
