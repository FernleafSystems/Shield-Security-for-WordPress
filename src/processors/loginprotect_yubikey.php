<?php

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_Yubikey', false ) ):
	return;
endif;

require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'loginprotect_intent_base.php' );

class ICWP_WPSF_Processor_LoginProtect_Yubikey extends ICWP_WPSF_Processor_LoginProtect_IntentBase {

	/**
	 * @const string
	 */
	const YubikeyVerifyApiUrl = 'https://api.yubico.com/wsapi/2.0/verify?id=%s&otp=%s&nonce=%s';

	/**
	 * @var ICWP_WPSF_Processor_LoginProtect_Track
	 */
	private $oLoginTrack;

	/**
	 */
	public function run() {

		if ( $this->getIsYubikeyConfigReady() ) {

			/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
			$oFO = $this->getFeatureOptions();

			if ( $oFO->getIfUseLoginIntentPage() ) {
				add_filter( $oFO->doPluginPrefix( 'login-intent-form-fields' ), array( $this, 'addLoginIntentField' ) );
				add_action( $oFO->doPluginPrefix( 'login-intent-validation' ), array( $this, 'validateLoginIntent' ) );
			}
			else {
				// after User has authenticated email/username/password
				add_filter( 'authenticate', array( $this, 'checkLoginForCode_Filter' ), 24, 1 );
				add_action( 'login_form',	array( $this, 'printLoginField' ) );
			}
		}
	}

	/**
	 * @param WP_User $oUser
	 * @return WP_User|WP_Error
	 */
	public function checkLoginForCode_Filter( $oUser ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		$oDp = $this->loadDataProcessor();
		$oLoginTrack = $this->getLoginTrack();

		$bNeedToCheckThisFactor = $oFO->isChainedAuth() || !$oLoginTrack->hasSuccessfulFactorAuth();
		$bErrorOnFailure = $bNeedToCheckThisFactor && $oLoginTrack->isFinalFactorRemainingToTrack();
		$oLoginTrack->addUnSuccessfulFactor( ICWP_WPSF_Processor_LoginProtect_Track::Factor_Yubikey );

		if ( !$bNeedToCheckThisFactor || empty( $oUser ) || is_wp_error( $oUser ) ) {
			return $oUser;
		}

		$oError = new WP_Error();
		$sUsername = $oUser->get( 'user_login' );

		$sOneTimePassword = $this->fetchCodeFromRequest();
//			$sApiKey = $this->getOption('yubikey_api_key');

		// check that if we have a list of permitted keys, that the one used is on that list connected with the username.
		$sYubikey12 = substr( $sOneTimePassword, 0 , 12 );
		$fUsernameFound = false; // if username is never found, it means there's no yubikey specified which means we can bypass this authentication method.
		$fFoundMatch = false;
		foreach( $this->getOption( 'yubikey_unique_keys' ) as $aUsernameYubikeyPair ) {
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
			$sAuditMessage = sprintf( _wpsf__('User "%s" attempted to login but Yubikey ID "%s" used was not in list of authorised keys.'), $sUsername, $sYubikey12 );
			$this->addToAuditEntry( $sAuditMessage, 2, 'login_protect_yubikey_fail_permitted_id' );

			if ( $bErrorOnFailure ) {
				$oError->add(
					'yubikey_not_allowed',
					sprintf( _wpsf__( 'ERROR: %s' ), _wpsf__('The Yubikey provided is not on the list of permitted keys for this user.') )
				);
				return $oError;
			}
		}

		if ( $this->processYubikeyOtp( $sOneTimePassword ) ) {
			$sAuditMessage = sprintf( _wpsf__('User "%s" successfully logged in using a validated Yubikey One Time Password.'), $sUsername );
			$this->addToAuditEntry( $sAuditMessage, 2, 'login_protect_yubikey_login_success' );
			$this->getLoginTrack()->addSuccessfulFactor( ICWP_WPSF_Processor_LoginProtect_Track::Factor_Yubikey );
		}
		else {

			$sAuditMessage = sprintf( _wpsf__('User "%s" attempted to login but Yubikey One Time Password failed to validate due to invalid Yubi API response.".'), $sUsername );
			$this->addToAuditEntry( $sAuditMessage, 2, 'login_protect_yubikey_fail_invalid_api_response' );

			$oError->add(
				'yubikey_validate_fail',
				sprintf( _wpsf__( 'ERROR: %s' ), _wpsf__( 'The Yubikey authentication was not validated successfully.' ) )
			);
		}

		return $bErrorOnFailure ? $oError : $oUser;
	}

	/**
	 * @param string $sOneTimePassword
	 * @return bool
	 */
	protected function processYubikeyOtp( $sOneTimePassword ) {

		$sNonce = md5( uniqid( rand() ) );
		$sUrl = sprintf( self::YubikeyVerifyApiUrl,
			$this->getOption( 'yubikey_app_id' ),
			$sOneTimePassword,
			$sNonce
		);
		$sRawYubiRequest = $this->loadFileSystemProcessor()->getUrlContent( $sUrl );

		$bMatchOtpAndNonce = preg_match( '/otp=' . $sOneTimePassword . '/', $sRawYubiRequest, $aMatches )
			&& preg_match( '/nonce=' . $sNonce . '/', $sRawYubiRequest, $aMatches );

		return $bMatchOtpAndNonce
			&& preg_match( '/status=([a-zA-Z0-9_]+)/', $sRawYubiRequest, $aMatchesStatus )
			&& ( $aMatchesStatus[ 1 ] == 'OK' ); // TODO: in preg_match
	}

	/**
	 */
	public function validateLoginIntent() {
		$oLoginTrack = $this->getLoginTrack();
		$oLoginTrack->addSuccessfulFactor( ICWP_WPSF_Processor_LoginProtect_Track::Factor_Yubikey );

		$oUser = $this->loadWpUsersProcessor()->getCurrentWpUser();
		if ( $this->userHasYubikeyEnabled( $oUser ) && !$this->processYubikeyOtp( $this->fetchCodeFromRequest() ) ) {
			$oLoginTrack->addUnSuccessfulFactor( ICWP_WPSF_Processor_LoginProtect_Track::Factor_Yubikey );
		}
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	protected function userHasYubikeyEnabled( $oUser ) {
		$sUsername = $oUser->get( 'user_login' );
		$bUsernameFound = false;
		foreach( $this->getOption( 'yubikey_unique_keys' ) as $aUsernameYubikeyPair ) {
			if ( isset( $aUsernameYubikeyPair[ $sUsername ] ) ) {
				$bUsernameFound = true;
				break;
			}
		}
		return $bUsernameFound;
	}

	/**
	 * @param array $aFields
	 * @return array
	 */
	public function addLoginIntentField( $aFields ) {
		if ( $this->userHasYubikeyEnabled( $this->loadWpUsersProcessor()->getCurrentWpUser() ) ) {
			$aFields[] = array(
				'name' => $this->getLoginFormParameter(),
				'type' => 'text',
				'value' => $this->fetchCodeFromRequest(),
				'text' => _wpsf__( 'Yubikey OTP' ),
				'help_link' => 'http://icwp.io/4i'
			);
		}
		return $aFields;
	}

	/**
	 */
	public function printLoginField() {
		echo $this->getLoginFormField();
	}

	/**
	 * @return string
	 */
	protected function getLoginFormField() {
		$sHtml =
			'<p class="yubi_otp">
				<label>%s<br />
					<input type="text" name="%s" class="input" value="" size="20" />
				</label>
			</p>
		';
		return sprintf( $sHtml,
			'<a href="http://icwp.io/4i" target="_blank">' . _wpsf__( 'Yubikey OTP' ) . '</a>',
			$this->getLoginFormParameter()
		);
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

	/**
	 * @return string
	 */
	protected function getLoginFormParameter() {
		return $this->getFeatureOptions()->prefixOptionKey( 'yubi_otp' );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Track
	 */
	public function getLoginTrack() {
		return $this->oLoginTrack;
	}

	/**
	 * @param ICWP_WPSF_Processor_LoginProtect_Track $oLoginTrack
	 * @return $this
	 */
	public function setLoginTrack( $oLoginTrack ) {
		$this->oLoginTrack = $oLoginTrack;
		return $this;
	}
}