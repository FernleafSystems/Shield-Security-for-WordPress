<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;

class LicenseLookup extends LicenseBase {

	public const SLUG = 'license_lookup';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$licHandler = self::con()->comps->license;

		$success = false;

		$checkInterval = $licHandler->getLicenseNotCheckedForInterval();
		if ( $checkInterval < 20 ) {
			$waitFor = 20 - $checkInterval;
			$msg = sprintf(
				__( 'Please wait %s before attempting another license check.', 'wp-simple-firewall' ),
				sprintf( _n( '%s second', '%s seconds', $waitFor, 'wp-simple-firewall' ), $waitFor )
			);
		}
		else {
			try {
				$success = $licHandler->verify( true )->hasValidWorkingLicense();
				$msg = $success ? __( 'Valid license found.', 'wp-simple-firewall' ) : __( "Valid license couldn't be found.", 'wp-simple-firewall' );
			}
			catch ( \Exception $e ) {
				$msg = $e->getMessage();
			}
		}

		$this->response()->setPayload( [
			'message'     => $msg,
			'page_reload' => true,
		] )->setPayloadSuccess( $success );
	}
}
