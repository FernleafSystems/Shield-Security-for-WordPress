<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;

class LicenseClear extends LicenseBase {

	public const SLUG = 'license_clear';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$licHandler = self::con()->comps->license;
		$licHandler->deactivate( false );
		$licHandler->clearLicense();
		$this->response()->setPayload( [
			'message'     => sprintf( __( '%s License Cleared', 'wp-simple-firewall' ), self::con()->labels->Name ),
			'page_reload' => true,
		] )->setPayloadSuccess( true );
	}
}
