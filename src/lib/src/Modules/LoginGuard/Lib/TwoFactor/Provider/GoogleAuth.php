<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use Dolondro\GoogleAuthenticator\{
	GoogleAuthenticator,
	Secret,
	SecretFactory
};
use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaGoogleAuthToggle;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Mfa\Ops as MfaDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\MfaRecordsHandler;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;
use Psr\Cache\InvalidArgumentException;

class GoogleAuth extends AbstractShieldProviderMfaDB {

	protected const SLUG = 'ga';

	/**
	 * @var Secret
	 */
	private $tempSecret;

	public static function ProviderEnabled() :bool {
		return parent::ProviderEnabled() && self::con()->opts->optIs( 'enable_google_authenticator', 'Y' );
	}

	protected function maybeMigrate() :void {
		$meta = self::con()->user_metas->for( $this->getUser() );
		$legacySecret = $meta->ga_secret;
		if ( !empty( $legacySecret ) && \strlen( $legacySecret ) === 16 && $meta->ga_validated ) {
			$this->createNewSecretRecord( $legacySecret, 'Google Auth' );
			unset( $meta->ga_secret );
			unset( $meta->ga_validated );
		}
	}

	public function getJavascriptVars() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getJavascriptVars(),
			[
				'ajax' => [
					'profile_ga_toggle' => ActionData::Build( MfaGoogleAuthToggle::class ),
				],
			]
		);
	}

	protected function getUserProfileFormRenderData() :array {
		$record = \current( $this->loadMfaRecords() );
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getUserProfileFormRenderData(),
			[
				'strings' => [
					'enter_auth_app_code'   => __( 'Enter 6-digit Code from App', 'wp-simple-firewall' ),
					'description_otp_code'  => __( 'Provide the current code generated by your Google Authenticator app.', 'wp-simple-firewall' ),
					'description_chart_url' => __( 'Use your Google Authenticator app to scan this QR code and enter the 6-digit one time password.', 'wp-simple-firewall' ),
					'description_ga_secret' => __( 'If you have a problem with scanning the QR code enter the long code manually into the app.', 'wp-simple-firewall' ),
					'desc_remove'           => __( 'Click to immediately remove Google Authenticator login authentication.', 'wp-simple-firewall' ),
					'label_check_to_remove' => sprintf( __( 'Remove %s', 'wp-simple-firewall' ), __( 'Google Authenticator', 'wp-simple-firewall' ) ),
					'label_enter_code'      => __( 'Google Authenticator Code', 'wp-simple-firewall' ),
					'label_ga_secret'       => __( 'Manual Code', 'wp-simple-firewall' ),
					'label_scan_qr_code'    => __( 'Scan This QR Code', 'wp-simple-firewall' ),
					'title'                 => __( 'Google Authenticator', 'wp-simple-firewall' ),
					'cant_add_other_user'   => sprintf( __( "Sorry, %s may not be added to another user's account.", 'wp-simple-firewall' ), 'Google Authenticator' ),
					'cant_remove_admins'    => sprintf( __( "Sorry, %s may only be removed from another user's account by a Security Administrator.", 'wp-simple-firewall' ), __( 'Google Authenticator', 'wp-simple-firewall' ) ),
					'provided_by'           => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ), self::con()
																										  ->getHumanName() ),
					'remove_more_info'      => __( 'Understand how to remove Google Authenticator', 'wp-simple-firewall' ),
					'remove_google_auth'    => __( 'Remove Google Authenticator', 'wp-simple-firewall' ),
					'generated_at'          => sprintf( '%s: %s', __( 'Registered', 'wp-simple-firewall' ),
						empty( $record ) ? '' : Services::Request()
														->carbon()
														->setTimestamp( $record->created_at )
														->diffForHumans()
					),
				],
				'vars'    => [
					'secret' => $this->isProfileActive() ? '' : $this->resetSecret(),
					'qr_url' => $this->getQrUrl(),
				],
			]
		);
	}

	private function getQrUrl() :string {
		$sec = $this->genTempSecret();
		return URL::Build( sprintf( 'otpauth://totp/%s', \urlencode( $sec->getIssuer().':'.$sec->getAccountName() ) ), [
			'secret' => $sec->getSecretKey(),
			'issuer' => $sec->getIssuer(),
			'label'  => $sec->getLabel(),
		] );
	}

	public function removeGA() :StdResponse {
		/** @var MfaDB\Delete $deleter */
		$deleter = self::con()->db_con->mfa->getQueryDeleter();
		$deleter->filterBySlug( $this::ProviderSlug() )
				->filterByUserID( $this->getUser()->ID )
				->query();

		$r = new StdResponse();
		$r->success = true;
		$r->msg_text = __( 'Google Authenticator was removed from the account.', 'wp-simple-firewall' );
		return $r;
	}

	public function activateGA( string $otp ) :StdResponse {
		$r = new StdResponse();

		$meta = self::con()->user_metas->for( $this->getUser() );
		try {
			if ( $this->hasValidSecret() ) {
				throw new \Exception( 'A GA profile already exists.' );
			}
			$r->success = ( new GoogleAuthenticator() )->authenticate( $meta->ga_temp_secret, $otp )
						  && $this->createNewSecretRecord( $meta->ga_temp_secret, 'Google Auth' );
			if ( $r->success ) {
				$r->msg_text = sprintf(
					__( '%s was successfully added to your account.', 'wp-simple-firewall' ),
					__( 'Google Authenticator', 'wp-simple-firewall' )
				);
			}
			else {
				$r->error_text = sprintf( '%s %s', __( 'Request Failed.', 'wp-simple-firewall' ),
					__( "OTP couldn't be verified.", 'wp-simple-firewall' ) );
			}
		}
		catch ( \Exception|InvalidArgumentException $e ) {
			$r->success = false;
			$r->error_text = sprintf( '%s %s', __( 'Failed to register.', 'wp-simple-firewall' ), $e->getMessage() );
		}
		finally {
			unset( $meta->ga_temp_secret );
		}

		return $r;
	}

	public function getFormField() :array {
		return [
			'slug'        => static::ProviderSlug(),
			'name'        => $this->getLoginIntentFormParameter(),
			'type'        => 'text',
			'value'       => '',
			'placeholder' => __( '123456', 'wp-simple-firewall' ),
			'text'        => __( 'Authenticator OTP', 'wp-simple-firewall' ),
			'description' => __( 'Enter 6-digit code from your authenticator app', 'wp-simple-firewall' ),
			'help_link'   => 'https://shsec.io/wpsf42',
			'extras'      => [
				'onkeyup' => "this.value=this.value.replace(/[^\d]/g,'')"
			]
		];
	}

	protected function processOtp( string $otp ) :bool {
		try {
			$valid = \preg_match( '#^\d{6}$#', $otp )
					 && ( new GoogleAuthenticator() )->authenticate( $this->getSecret()->unique_id, $otp );
			if ( $valid ) {
				( new MfaRecordsHandler() )->update( $this->getSecret(), [
					'used_at' => Services::Request()->ts()
				] );
			}
		}
		catch ( \Exception|\Psr\Cache\CacheException $e ) {
			$valid = false;
		}
		return $valid;
	}

	protected function genNewSecret() :string {
		return $this->genTempSecret()->getSecretKey();
	}

	private function genTempSecret() :Secret {
		if ( !isset( $this->tempSecret ) ) {
			$this->tempSecret = ( new SecretFactory() )->create(
				\preg_replace( '#[^\da-z]#i', '', Services::WpGeneral()->getSiteName() ),
				sanitize_user( $this->getUser()->user_login )
			);
		}
		return $this->tempSecret;
	}

	public function resetSecret() :string {
		return self::con()->user_metas->for( $this->getUser() )->ga_temp_secret = $this->genNewSecret();
	}

	protected function isValidSecret( $secret ) :bool {
		return parent::isValidSecret( $secret ) && \strlen( $secret->unique_id ) === 16;
	}

	public static function ProviderName() :string {
		return __( 'Google Authenticator' );
	}
}