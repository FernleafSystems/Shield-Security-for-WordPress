<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions,
	Actions\MfaEmailSendIntent,
	Actions\MfaPasskeyAuthenticationStart
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\LoginRequestValues;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Passkey;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-import-type LoginIntentRenderData from LoginRequestValues
 */
abstract class BaseLoginIntentPage extends Actions\Render\FullPage\BaseFullPageRender {

	use Actions\Traits\AuthNotRequired;

	public function getLoginIntentJavascript() :array {
		$data = $this->loginIntentRenderData();
		$user = Services::WpUsers()->getUserById( $data[ 'user_id' ] );
		$prov = $user instanceof \WP_User ? self::con()->comps->mfa->getProvidersActiveForUser( $user ) : [];

		return [
			'ajax'  => [
				'passkey_auth_start' => ActionData::Build( MfaPasskeyAuthenticationStart::class, true, [
					'login_wp_user' => $data[ 'user_id' ],
					'login_nonce'   => $data[ 'plain_login_nonce' ],
				] ),
				'email_code_send'    => ActionData::Build( MfaEmailSendIntent::class, true, [
					'wp_user_id'  => $data[ 'user_id' ],
					'login_nonce' => $data[ 'plain_login_nonce' ],
					'redirect_to' => esc_url_raw( $data[ 'redirect_to' ] ),
				] ),
			],
			'flags' => [
				'passkey_auth_auto' => \count( $prov ) === 1 && isset( $prov[ Passkey::ProviderSlug() ] ),
			],
		];
	}

	/**
	 * @return LoginIntentRenderData
	 */
	protected function loginIntentRenderData() :array {
		/** @var LoginIntentRenderData $data */
		$data = $this->action_data;
		return $data;
	}
}
