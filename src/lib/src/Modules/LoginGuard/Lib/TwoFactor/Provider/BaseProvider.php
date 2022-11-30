<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 17.0
 */
abstract class BaseProvider extends AbstractOtpProvider {

	use Modules\ModConsumer;

	const DEFAULT_SECRET = '';

	public function getJavascriptVars() :array {
		return [];
	}

	/**
	 * @return string|array|mixed
	 */
	protected function getSecret() {
		$secret = $this->getCon()->getUserMeta( $this->getUser() )->{static::ProviderSlug().'_secret'};
		return empty( $secret ) ? static::DEFAULT_SECRET : $secret;
	}

	public function hasValidatedProfile() :bool {
		return $this->getCon()->getUserMeta( $this->getUser() )->{static::ProviderSlug().'_validated'} === true;
	}

	protected function hasValidSecret() :bool {
		$secret = $this->getSecret();
		return !empty( $secret ) && is_string( $secret );
	}

	public function isEnforced() :bool {
		return false;
	}

	public function isProfileActive() :bool {
		return $this->hasValidatedProfile() && $this->isProviderAvailableToUser();
	}

	public function isProviderAvailableToUser() :bool {
		return $this->isProviderEnabled();
	}

	/**
	 * @return mixed
	 */
	public function resetSecret() {
		$newSecret = $this->genNewSecret();
		$this->setSecret( $newSecret );
		return $newSecret;
	}

	public function removeFromProfile() {
		$this->getCon()->getUserMeta( $this->getUser() )->{static::ProviderSlug().'_secret'} = null;
		$this->setProfileValidated( false );
	}

	/**
	 * @return $this
	 */
	public function setProfileValidated( bool $validated ) {
		$this->getCon()
			 ->getUserMeta( $this->getUser() )->{static::ProviderSlug().'_validated'} = $validated;
		return $this;
	}

	/**
	 * @param string|array $secret
	 * @return $this
	 */
	protected function setSecret( $secret ) {
		$this->getCon()
			 ->getUserMeta( $this->getUser() )->{static::ProviderSlug().'_secret'} = $secret;
		return $this;
	}

	/**
	 * @return string|mixed
	 */
	protected function genNewSecret() {
		return '';
	}

	/**
	 * Only to be fired if and when Login has been completely verified.
	 * @return $this
	 */
	public function postSuccessActions() {
		$this->getCon()
			 ->getUserMeta( $this->getUser() )->record->last_2fa_verified_at = Services::Request()->ts();
		return $this;
	}

	public function getUserProfileFormRenderData() :array {
		return [
			'flags'   => [
				'has_validated_profile' => $this->hasValidatedProfile(),
				'is_enforced'           => $this->isEnforced(),
				'is_profile_active'     => $this->isProfileActive(),
				'user_to_edit_is_admin' => Services::WpUsers()->isUserAdmin( $this->getUser() ),
				'show_explanatory_text' => false
			],
			'vars'    => [
				'otp_field_name' => $this->getLoginIntentFormParameter(),
				'provider_slug'  => static::ProviderSlug(),
			],
			'strings' => [
				'is_enforced'   => __( 'This setting is enforced by your security administrator.', 'wp-simple-firewall' ),
				'provider_name' => $this->getProviderName()
			],
		];
	}

	public function getFormField() :array {
		return [];
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

	public function getLoginIntentFormParameter() :string {
		return $this->getCon()->prefixOption( static::ProviderSlug().'_otp' );
	}
}