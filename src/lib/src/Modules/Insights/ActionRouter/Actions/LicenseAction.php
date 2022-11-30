<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class LicenseAction extends LicenseBase {

	public const SLUG = 'license_action';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$licHandler = $mod->getLicenseHandler();

		$success = false;
		$msg = 'Unsupported license action';

		$licenseAction = Services::Request()->post( 'license-action' );

		if ( $licenseAction == 'clear' ) {
			$success = true;
			$licHandler->deactivate( false );
			$licHandler->clearLicense();
			$msg = __( 'Success', 'wp-simple-firewall' ).'! '.__( 'Reloading page', 'wp-simple-firewall' ).'...';
		}
		elseif ( $licenseAction == 'check' ) {

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
		}

		$this->response()->action_response_data = [
			'success' => $success,
			'message' => $msg,
		];
	}
}