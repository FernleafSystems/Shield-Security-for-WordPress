<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class LicenseLookup extends LicenseBase {

	public const SLUG = 'license_lookup';

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

		$this->response()->action_response_data = [
			'success'     => $success,
			'message'     => $msg,
			'page_reload' => true,
		];
	}
}