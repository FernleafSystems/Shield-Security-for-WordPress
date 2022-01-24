<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseProvider {

	use Modules\ModConsumer;

	const SLUG = '';
	/**
	 * Set to true if this provider can be used in isolation. False if there
	 * must be at least 1 other 2FA provider active alongside it.
	 */
	const STANDALONE = true;
	const DEFAULT_SECRET = '';

	/**
	 * @var \WP_User
	 */
	private $user;

	public function __construct() {
	}

	public function getJavascriptVars() :array {
		return [];
	}

	abstract public function getProviderName() :string;

	/**
	 * Assumes this is only called on active profiles
	 */
	public function validateLoginIntent() :bool {
		$otpSuccess = false;
		$otp = $this->fetchCodeFromRequest();
		if ( !empty( $otp ) ) {
			$otpSuccess = $this->processOtp( $otp );
			$this->auditLogin( $otpSuccess );
		}
		return $otpSuccess;
	}

	/**
	 * @return string|array|mixed
	 */
	protected function getSecret() {
		$secret = $this->getCon()->getUserMeta( $this->getUser() )->{static::SLUG.'_secret'};
		return empty( $secret ) ? static::DEFAULT_SECRET : $secret;
	}

	public function hasValidatedProfile() :bool {
		return $this->getCon()->getUserMeta( $this->getUser() )->{static::SLUG.'_validated'} === true;
	}

	protected function hasValidSecret() :bool {
		$secret = $this->getSecret();
		return !empty( $secret ) && is_string( $secret );
	}

	protected function isEnforced() :bool {
		return false;
	}

	public function isProfileActive() :bool {
		return $this->hasValidatedProfile() && $this->isProviderAvailableToUser();
	}

	public function isProviderAvailableToUser() :bool {
		return $this->isProviderEnabled();
	}

	abstract public function isProviderEnabled() :bool;

	/**
	 * @deprecated 13.1
	 */
	protected function isSecretValid() :bool {
		return false;
	}

	/**
	 * @return mixed
	 */
	public function resetSecret() {
		$newSecret = $this->genNewSecret();
		$this->setSecret( $newSecret );
		return $newSecret;
	}

	public function remove() {
		$this->getCon()->getUserMeta( $this->getUser() )->{static::SLUG.'_secret'} = null;
		$this->setProfileValidated( false );
	}

	/**
	 * @return $this
	 */
	public function setProfileValidated( bool $validated ) {
		$this->getCon()
			 ->getUserMeta( $this->getUser() )->{static::SLUG.'_validated'} = $validated;
		return $this;
	}

	/**
	 * @param string|array $secret
	 * @return $this
	 */
	protected function setSecret( $secret ) {
		$this->getCon()
			 ->getUserMeta( $this->getUser() )->{static::SLUG.'_secret'} = $secret;
		return $this;
	}

	/**
	 * @return string|mixed
	 */
	protected function genNewSecret() {
		return '';
	}

	abstract protected function processOtp( string $otp ) :bool;

	/**
	 * Only to be fired if and when Login has been completely verified.
	 * @return $this
	 */
	public function postSuccessActions() {
		$this->getCon()
			 ->getUserMeta( $this->getUser() )->record->last_2fa_verified_at = Services::Request()->ts();
		return $this;
	}

	public function renderUserProfileCustomForm() :string {
		$user = $this->getUser();
		$data = Services::DataManipulation()->mergeArraysRecursive(
			[
				'flags'   => [
					'has_validated_profile' => $this->hasValidatedProfile(),
					'is_enforced'           => $this->isEnforced(),
					'is_profile_active'     => $this->isProfileActive(),
					'user_to_edit_is_admin' => Services::WpUsers()->isUserAdmin( $user ),
					'show_explanatory_text' => true
				],
				'vars'    => [
					'otp_field_name' => $this->getLoginFormParameter(),
				],
				'strings' => [
					'is_enforced'   => __( 'This setting is enforced by your security administrator.', 'wp-simple-firewall' ),
					'provider_name' => $this->getProviderName()
				],
			],
			$this->getProviderSpecificRenderData()
		);
		$data[ 'flags' ][ 'show_explanatory_text' ] = false;
		return $this->getMod()
					->renderTemplate(
						sprintf( '/user/profile/mfa/provider_%s.twig', static::SLUG ),
						$data
					);
	}

	protected function getProviderSpecificRenderData() :array {
		return [];
	}

	public function captureLoginAttempt() {
	}

	public function getFormField() :array {
		return [];
	}

	public function renderFormFieldForWpLogin() :string {
		return $this->getMod()->renderTemplate(
			sprintf( '/components/wplogin_replica/login_field_%s.twig', static::SLUG ),
			$this->getFormField()
		);
	}

	protected function auditLogin( bool $success ) {
		$this->getCon()->fireEvent(
			$success ? '2fa_verify_success' : '2fa_verify_fail',
			[
				'audit_params' => [
					'user_login' => $this->getUser()->user_login,
					'method'     => $this->getProviderName(),
				]
			]
		);
	}

	protected function getLoginFormParameter() :string {
		return $this->getCon()->prefixOption( static::SLUG.'_otp' );
	}

	protected function fetchCodeFromRequest() :string {
		return trim( (string)Services::Request()->request( $this->getLoginFormParameter(), false, '' ) );
	}

	protected function getCommonData() :array {
		$user = $this->getUser();
		return [
			'flags'   => [
				'has_validated_profile' => $this->hasValidatedProfile(),
				'is_enforced'           => $this->isEnforced(),
				'is_profile_active'     => $this->isProfileActive(),
				'user_to_edit_is_admin' => Services::WpUsers()->isUserAdmin( $user ),
				'show_explanatory_text' => true
			],
			'vars'    => [
				'otp_field_name' => $this->getLoginFormParameter(),
			],
			'strings' => [
				'is_enforced'   => __( 'This setting is enforced by your security administrator.', 'wp-simple-firewall' ),
				'provider_name' => $this->getProviderName()
			],
		];
	}

	protected function generateSimpleOTP( int $length = 6 ) :string {
		do {
			$otp = substr( strtoupper( preg_replace( '#[io01l]#i', '', wp_generate_password( 50, false ) ) ), 0, $length );
		} while ( strlen( $otp ) !== $length );
		return $otp;
	}

	protected function getUser() :\WP_User {
		return $this->user ?? Services::WpUsers()->getCurrentWpUser();
	}

	/**
	 * @return $this
	 */
	public function setUser( \WP_User $user ) {
		$this->user = $user;
		return $this;
	}
}