<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Licenses\Keyless;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function getAjaxActionCallbackMap( bool $isAuth ) :array {
		$map = parent::getAjaxActionCallbackMap( $isAuth );
		if ( $isAuth ) {
			$map = array_merge( $map, [
				'license_action' => [ $this, 'ajaxExec_LicenseHandling' ],
				'connection_debug' => [ $this, 'ajaxExec_ConnectionDebug' ],
			] );
		}
		return $map;
	}

	public function ajaxExec_ConnectionDebug() :array {
		$success = ( new Keyless\Ping() )->ping();
		$host = wp_parse_url( Keyless\Base::DEFAULT_URL_STUB, PHP_URL_HOST );

		if ( $success ) {
			$msg = 'Successfully connected to license server.';
		}
		elseif ( !Services::IP()->isValidIp( gethostbyname( $host ) ) ) {
			$msg = sprintf( 'Could not resolve host IP address: %s', $host );
		}
		else {
			$msg = 'Failed to connect to license server.';
		}

		return [
			'success' => $success,
			'message' => $msg
		];
	}

	public function ajaxExec_LicenseHandling() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
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
					$success = $licHandler->verify()->hasValidWorkingLicense();
					$msg = $success ? __( 'Valid license found.', 'wp-simple-firewall' ) : __( "Valid license couldn't be found.", 'wp-simple-firewall' );
				}
				catch ( \Exception $e ) {
					$msg = $e->getMessage();
				}
			}
		}

		return [
			'success' => $success,
			'message' => $msg,
		];
	}
}