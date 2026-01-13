<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions,
	Actions\MfaEmailSendIntent,
	Actions\MfaPasskeyAuthenticationStart
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Passkey;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseLoginIntentPage extends Actions\Render\FullPage\BaseFullPageRender {

	use Actions\Traits\AuthNotRequired;

	public function getLoginIntentJavascript() :array {
		$userID = (int)$this->action_data[ 'user_id' ] ?? 0;
		$loginNonce = (string)$this->action_data[ 'plain_login_nonce' ] ?? '';

		$prov = self::con()->comps->mfa->getProvidersActiveForUser(
			Services::WpUsers()->getUserById( $userID )
		);

		return [
			'ajax'  => [
				'passkey_auth_start' => ActionData::Build( MfaPasskeyAuthenticationStart::class, true, [
					'login_wp_user' => $userID,
					'login_nonce'   => $loginNonce,
				] ),
				'email_code_send'    => ActionData::Build( MfaEmailSendIntent::class, true, [
					'wp_user_id'  => $userID,
					'login_nonce' => $loginNonce,
					'redirect_to' => esc_url_raw( $this->action_data[ 'redirect_to' ] ?? '' ),
				] ),
			],
			'flags' => [
				'passkey_auth_auto' => \count( $prov ) === 1 && isset( $prov[ Passkey::ProviderSlug() ] ),
			],
		];
	}
}