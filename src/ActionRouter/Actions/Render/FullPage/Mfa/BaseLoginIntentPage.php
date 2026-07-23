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

abstract class BaseLoginIntentPage extends Actions\Render\FullPage\BaseFullPageRender {

	use Actions\Traits\AuthNotRequired;

	public function getLoginIntentJavascript() :array {
		$userID = LoginRequestValues::positiveUserId( $this->action_data[ 'user_id' ] ?? null );
		$loginNonce = LoginRequestValues::nonEmptyString( $this->action_data[ 'plain_login_nonce' ] ?? null ) ?? '';
		$user = $userID === null ? null : Services::WpUsers()->getUserById( $userID );
		$prov = $user instanceof \WP_User ? self::con()->comps->mfa->getProvidersActiveForUser( $user ) : [];
		$redirectFallback = Services::Request()->getPath();

		return [
			'ajax'  => [
				'passkey_auth_start' => ActionData::Build( MfaPasskeyAuthenticationStart::class, true, [
					'login_wp_user' => $userID ?? 0,
					'login_nonce'   => $loginNonce,
				] ),
				'email_code_send'    => ActionData::Build( MfaEmailSendIntent::class, true, [
					'wp_user_id'  => $userID ?? 0,
					'login_nonce' => $loginNonce,
					'redirect_to' => esc_url_raw( LoginRequestValues::safeRedirect(
						$this->action_data[ 'redirect_to' ] ?? null,
						$redirectFallback
					) ),
				] ),
			],
			'flags' => [
				'passkey_auth_auto' => \count( $prov ) === 1 && isset( $prov[ Passkey::ProviderSlug() ] ),
			],
		];
	}
}
