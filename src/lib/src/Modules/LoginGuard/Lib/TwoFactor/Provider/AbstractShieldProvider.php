<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;
use FernleafSystems\Wordpress\Services\Services;

abstract class AbstractShieldProvider extends AbstractOtpProvider {

	public function getJavascriptVars() :array {
		return [];
	}

	/**
	 * @return string|array|mixed
	 */
	protected function getSecret() {
		$secret = $this->con()->user_metas->for( $this->getUser() )->{static::ProviderSlug().'_secret'};
		return empty( $secret ) ? '' : $secret;
	}

	public function hasValidatedProfile() :bool {
		return $this->con()->user_metas->for( $this->getUser() )->{static::ProviderSlug().'_validated'} === true;
	}

	protected function hasValidSecret() :bool {
		$secret = $this->getSecret();
		return !empty( $secret ) && is_string( $secret );
	}

	public function isProfileActive() :bool {
		return $this->hasValidatedProfile() && $this->isProviderAvailableToUser();
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
		$this->con()->user_metas->for( $this->getUser() )->{static::ProviderSlug().'_secret'} = null;
		$this->setProfileValidated( false );
	}

	/**
	 * @return $this
	 */
	public function setProfileValidated( bool $validated ) {
		$this->con()->user_metas->for( $this->getUser() )->{static::ProviderSlug().'_validated'} = $validated;
		return $this;
	}

	/**
	 * @param string|array $secret
	 * @return $this
	 */
	protected function setSecret( $secret ) {
		$this->con()->user_metas->for( $this->getUser() )->{static::ProviderSlug().'_secret'} = $secret;
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
		$this->con()->user_metas->for( $this->getUser() )->record->last_2fa_verified_at = Services::Request()->ts();
		return $this;
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
		$this->con()->fireEvent(
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
		return $this->con()->prefixOption( static::ProviderSlug().'_otp' );
	}

	public function renderUserProfileConfigFormField() :string {
		return $this->con()->action_router->render(
			Render\Components\UserMfa\ConfigFormForProvider::SLUG,
			$this->getUserProfileFormRenderData()
		);
	}

	protected function renderLoginIntentFormFieldForShield() :string {
		return $this->con()->action_router->render(
			Render\GenericRender::SLUG,
			[
				'render_action_template' => sprintf( '/components/login_intent/login_field_%s.twig', static::ProviderSlug() ),
				'render_action_data'     => [
					'field' => $this->getFormField()
				],
			]
		);
	}

	protected function renderLoginIntentFormFieldForWpLoginReplica() :string {
		return $this->con()->action_router->render(
			Render\GenericRender::SLUG,
			[
				'render_action_template' => sprintf( '/components/wplogin_replica/login_field_%s.twig', static::ProviderSlug() ),
				'render_action_data'     => [
					'field' => $this->getFormField()
				],
			]
		);
	}
}