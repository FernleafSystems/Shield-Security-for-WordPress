<?php

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_Yubikey', false ) ):

	require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_wpsf.php' );

	class ICWP_WPSF_Processor_LoginProtect_Yubikey extends ICWP_WPSF_Processor_BaseWpsf {

		/**
		 * @const string
		 */
		const YubikeyVerifyApiUrl = 'https://api.yubico.com/wsapi/2.0/verify?id=%s&otp=%s&nonce=%s';

		/**
		 */
		public function run() {
			if ( $this->getIsYubikeyConfigReady() ) {
				add_filter( 'wp_authenticate_user', array( $this, 'checkYubikeyOtpAuth_Filter' ) );
				add_action( 'login_form',			array( $this, 'printYubikeyOtp_Action' ) );
			}
		}

		/**
		 * @param WP_User $oUser
		 * @return WP_User|WP_Error
		 */
		public function checkYubikeyOtpAuth_Filter( $oUser ) {
			$oError = new WP_Error();
			$sUsername = $oUser->get( 'user_login' );

			// Before anything else we check that a Yubikey pair has been provided for this username (and that there are pairs in the first place!)
			$aYubikeyUsernamePairs = $this->getOption('yubikey_unique_keys');
			if ( !$this->getIsYubikeyConfigReady() ) { // configuration is clearly not completed yet.
				return $oUser;
			}

			$sOneTimePassword =  empty( $_POST['yubiotp'] )? '' : trim( $_POST['yubiotp'] );
			$sAppId = $this->getOption('yubikey_app_id');
			$sApiKey = $this->getOption('yubikey_api_key');

			// check that if we have a list of permitted keys, that the one used is on that list connected with the username.
			$sYubikey12 = substr( $sOneTimePassword, 0 , 12 );
			$fUsernameFound = false; // if username is never found, it means there's no yubikey specified which means we can bypass this authentication method.
			$fFoundMatch = false;
			foreach( $aYubikeyUsernamePairs as $aUsernameYubikeyPair ) {
				if ( isset( $aUsernameYubikeyPair[ $sUsername ] ) ) {
					$fUsernameFound = true;
					if ( $aUsernameYubikeyPair[ $sUsername ] == $sYubikey12 ) {
						$fFoundMatch = true;
						break;
					}
				}
			}

			// If no yubikey-username pair found for given username, we by-pass Yubikey auth.
			if ( !$fUsernameFound ) {
				$sAuditMessage = sprintf( _wpsf__('User "%s" logged in without a Yubikey One Time Password because no username-yubikey pair was found for this user.'), $sUsername );
				$this->addToAuditEntry( $sAuditMessage, 2, 'login_protect_yubikey_bypass' );
				return $oUser;
			}

			// Username was found in the list of key pairs, but the yubikey provided didn't match that username.
			if ( !$fFoundMatch ) {
				$oError->add(
					'yubikey_not_allowed',
					sprintf( _wpsf__( 'ERROR: %s' ), _wpsf__('The Yubikey provided is not on the list of permitted keys for this user.') )
				);
				$sAuditMessage = sprintf( _wpsf__('User "%s" attempted to login but Yubikey ID "%s" used was not in list of authorised keys.'), $sUsername, $sYubikey12 );
				$this->addToAuditEntry( $sAuditMessage, 2, 'login_protect_yubikey_fail_permitted_id' );
				return $oError;
			}

			$oFs = $this->loadFileSystemProcessor();

			$sNonce = md5( uniqid( rand() ) );
			$sUrl = sprintf( self::YubikeyVerifyApiUrl, $sAppId, $sOneTimePassword, $sNonce );
			$sRawYubiRequest = $oFs->getUrlContent( $sUrl );

			// Validate response.
			// 1. Check OTP and Nonce
			if ( !preg_match( '/otp='.$sOneTimePassword.'/', $sRawYubiRequest, $aMatches )
				|| !preg_match( '/nonce='.$sNonce.'/', $sRawYubiRequest, $aMatches )
			) {
				$oError->add(
					'yubikey_validate_fail',
					sprintf( _wpsf__( 'ERROR: %s' ), _wpsf__('The Yubikey authentication was not validated successfully.') )
				);
				$sAuditMessage = sprintf( _wpsf__('User "%s" attempted to login but Yubikey One Time Password failed to validate due to invalid Yubi API.'), $sUsername );
				$this->addToAuditEntry( $sAuditMessage, 2, 'login_protect_yubikey_fail_invalid_api' );
				return $oError;
			}

			// Optionally we can check the hash, but since we're using HTTPS, this isn't necessary and adds more PHP requirements

			// 2. Check status directly within response
			preg_match( '/status=([a-zA-Z0-9_]+)/', $sRawYubiRequest, $aMatches );
			$sStatus = $aMatches[1];

			if ( $sStatus != 'OK' && $sStatus != 'REPLAYED_OTP' ) {
				$oError->add(
					'yubikey_validate_fail',
					sprintf( _wpsf__( 'ERROR: %s' ), _wpsf__('The Yubikey authentication was not validated successfully.') )
				);
				$sAuditMessage = sprintf( _wpsf__('User "%s" attempted to login but Yubikey One Time Password failed to validate due to invalid Yubi API response status: "%s".'), $sUsername, $sStatus );
				$this->addToAuditEntry( $sAuditMessage, 2, 'login_protect_yubikey_fail_invalid_api_response' );
				return $oError;
			}

			$sAuditMessage = sprintf( _wpsf__('User "%s" successfully logged in using a validated Yubikey One Time Password.'), $sUsername );
			$this->addToAuditEntry( $sAuditMessage, 2, 'login_protect_yubikey_login_success' );
			return $oUser;
		}

		/**
		 */
		public function printYubikeyOtp_Action() {
			$sHtml =
				'<p class="yubikey-otp">
				<label>%s<br />
					<input type="text" name="yubiotp" class="input" value="" size="20" />
				</label>
			</p>
		';
			echo sprintf( $sHtml, '<a href="http://icwp.io/4i" target="_blank">'._wpsf__('Yubikey OTP').'</a>' );
		}

		/**
		 * @return bool
		 */
		protected function getIsYubikeyConfigReady() {
			$sAppId = $this->getOption('yubikey_app_id');
			$sApiKey = $this->getOption('yubikey_api_key');
			$aYubikeyKeys = $this->getOption('yubikey_unique_keys');
			return !empty($sAppId) && !empty($sApiKey) && !empty($aYubikeyKeys);
		}
	}
endif;
