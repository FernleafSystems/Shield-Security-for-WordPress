<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class AbstractShieldProvider extends AbstractOtpProvider {

	use PluginControllerConsumer;

	public static function ProviderEnabled() :bool {
		return self::con()->comps->opts_lookup->isPluginEnabled();
	}

	public function getJavascriptVars() :array {
		return [
			'flags'   => [
				'is_available' => $this->isProviderAvailableToUser(),
			],
			'strings' => [
				'err_no_label'        => __( 'Device registration may not proceed without a unique label.', 'wp-simple-firewall' ),
				'err_invalid_label'   => __( 'Device label must contain letters, numbers, underscore, or hypen, and be no more than 16 characters.', 'wp-simple-firewall' ),
				'label_prompt_dialog' => __( 'Please provide a label to identify the device.', 'wp-simple-firewall' ),
			]
		];
	}

	/**
	 * @return string|array|mixed
	 */
	protected function getSecret() {
		$secret = self::con()->user_metas->for( $this->getUser() )->{static::ProviderSlug().'_secret'};
		return empty( $secret ) ? '' : $secret;
	}

	public function hasValidatedProfile() :bool {
		return self::con()->user_metas->for( $this->getUser() )->{static::ProviderSlug().'_validated'} === true;
	}

	protected function hasValidSecret() :bool {
		$secret = $this->getSecret();
		return !empty( $secret ) && \is_string( $secret );
	}

	public function isProfileActive() :bool {
		return $this->hasValidatedProfile() && $this->isProviderAvailableToUser();
	}

	public function isProviderEnabled() :bool {
		return static::ProviderEnabled();
	}

	/**
	 * @return mixed
	 */
	public function resetSecret() {
		$newSecret = $this->genNewSecret();
		$this->setSecret( $newSecret );
		return $newSecret;
	}

	public function removeFromProfile() :void {
		self::con()->user_metas->for( $this->getUser() )->{static::ProviderSlug().'_secret'} = null;
		$this->setProfileValidated( false );
	}

	/**
	 * @return $this
	 */
	public function setProfileValidated( bool $validated ) {
		self::con()->user_metas->for( $this->getUser() )->{static::ProviderSlug().'_validated'} = $validated;
		return $this;
	}

	/**
	 * @param string|array $records
	 * @return $this
	 */
	protected function setSecret( $records ) {
		self::con()->user_metas->for( $this->getUser() )->{static::ProviderSlug().'_secret'} = $records;
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
	 * @return void
	 */
	public function postSuccessActions() :void {
		self::con()->user_metas->for( $this->getUser() )->record->last_2fa_verified_at = Services::Request()->ts();
	}

	protected function getUserProfileFormRenderData() :array {
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
		self::con()->comps->events->fireEvent(
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
		return self::con()->prefix( static::ProviderSlug().'_otp', '_' );
	}

	public function renderUserProfileConfigFormField() :string {
		return self::con()->action_router->render(
			Render\Components\UserMfa\ConfigFormForProvider::SLUG,
			$this->getUserProfileFormRenderData()
		);
	}

	protected function renderLoginIntentFormFieldForShield() :string {
		return self::con()->action_router->render(
			Render\Components\UserMfa\LoginIntent\LoginIntentFormFieldShield::class,
			[
				'vars' => [
					'provider_slug' => static::ProviderSlug(),
					'field'         => $this->getFormField()
				]
			]
		);
	}

	protected function renderLoginIntentFormFieldForWpLoginReplica() :string {
		return self::con()->action_router->render(
			Render\Components\UserMfa\LoginIntent\LoginIntentFormFieldWpReplica::class,
			[
				'vars' => [
					'provider_slug' => static::ProviderSlug(),
					'field'         => $this->getFormField()
				]
			]
		);
	}
}