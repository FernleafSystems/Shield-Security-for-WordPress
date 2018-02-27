<?php

if ( class_exists( 'ICWP_WPSF_Processor_UserManagement_Sessions', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

/**
 * Referenced some of https://github.com/BenjaminNelan/PwnedPasswordChecker
 * Class ICWP_WPSF_Processor_UserManagement_Pwned
 */
class ICWP_WPSF_Processor_UserManagement_Pwned extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		// Account Reg
		add_filter( 'registration_errors', array( $this, 'checkPassword' ), 100, 3 );
		// Profile Update
		add_action( 'user_profile_update_errors', array( $this, 'checkPassword' ), 100, 3 );
		// Reset
		add_action( 'validate_password_reset', array( $this, 'checkPassword' ), 100, 3 );
		// Login
		add_filter( 'authenticate', array( $this, 'checkPassword' ), 100, 3 );
	}

	/**
	 * @param WP_Error $oErrors
	 * @return WP_Error
	 */
	public function checkPassword( $oErrors ) {
		$aExistingCodes = $oErrors->get_error_code();
		if ( empty( $aExistingCodes ) ) {

			$sPassword = $this->loadDP()->post( 'pass1' );
			if ( !empty( $sPassword ) ) {
				try {
					$this->sendRequestToPwned( $sPassword );
				}
				catch ( Exception $oE ) {
					$oErrors->add( 'shield_pwned_password', $oE->getMessage() );
				}
			}
		}

		return $oErrors;
	}

	/**
	 * @return bool
	 */
	protected function verifyApiAccess() {
		try {
			$this->sendRequestToPwned( 'P@ssw0rd' );
		}
		catch ( Exception $oE ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $sPass
	 * @return bool
	 * @throws Exception
	 */
	protected function sendRequestToPwned( $sPass ) {
		$oConn = $this->getController();
		$aResponse = $this->loadFS()->requestUrl(
			sprintf( 'https://api.pwnedpasswords.com/pwnedpassword/%s', hash( 'sha1', $sPass ) ),
			array(
				'headers' => array(
					'user-agent' => sprintf( '%s WP Plugin-v%s', $oConn->getHumanName(), $oConn->getVersion() )
				)
			),
			true
		);

		$sError = '';
		if ( is_wp_error( $aResponse ) ) {
			$sError = $aResponse->get_error_message();
		}
		else if ( empty( $aResponse ) ) {
			$sError = 'Response was empty';
		}
		else if ( is_array( $aResponse ) ) {
			if ( empty( $aResponse[ 'response' ][ 'code' ] ) ) {
				$sError = 'Unexpected Error: No response code available from the API';
			}
			else if ( $aResponse[ 'response' ][ 'code' ] == 404 ) {
				// means that the password isn't on the pwned list. It's acceptable.
			}
			else if ( empty( $aResponse[ 'body' ] ) ) {
				$sError = 'Unexpected Error: The response from the API was empty';
			}
			else {
				// password pwned
				$nCount = intval( preg_replace( '#[^0-9]#', '', $aResponse[ 'body' ] ) );
				$sError = _wpsf__( 'Please use a different password.' )
						  .' '._wpsf__( 'This password has already been pwned.' )
						  .' '.sprintf(
							  '(<a href="%s" target="_blank">%s</a>)',
							  'https://www.troyhunt.com/ive-just-launched-pwned-passwords-version-2/',
							  sprintf( _wpsf__( '%s times' ), $nCount )
						  );
			}
		}

		if ( !empty( $sError ) ) {
			throw new Exception( $sError );
		}

		return true;
	}
}