<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Traits\{
	NonceVerifyNotRequired,
	SecurityAdminNotRequired
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class UserMfaConfigForm extends BaseRender {

	use NonceVerifyNotRequired;
	use SecurityAdminNotRequired;

	const SLUG = 'user_mfa_config_form';
	const PRIMARY_MOD = 'login_protect';
	const TEMPLATE = '/user/profile/mfa/main.twig';

	protected function getDefaults() :array {
		return [
			'user_id'  => Services::WpUsers()->getCurrentWpUserId(),
			'title'    => __( 'Multi-Factor Authentication', 'wp-simple-firewall' ),
			'subtitle' => __( 'Provided by Shield', 'wp-simple-firewall' ),
		];
	}

	protected function getRenderData() :array {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$mfaCon = $mod->getMfaController();
		$user = Services::WpUsers()->getUserById( $this->action_data[ 'user_id' ] );

		$providerRenders = array_map(
			function ( $provider ) use ( $user ) {
				return $provider->setUser( $user )->renderUserProfileCustomForm();
			},
			$user instanceof \WP_User ? $mfaCon->getProvidersForUser( $user ) : []
		);

		return apply_filters( 'shield/render_data_custom_profiles_mfa', [
			'content' => [
				'providers' => $providerRenders,
			],
			'flags'   => [
				'has_providers' => !empty( $providerRenders ),
				'logged_in'     => $user instanceof \WP_User,
			],
			'strings' => [
				'title'         => esc_html( $this->action_data[ 'title' ] ?? 'MFA' ),
				'subtitle'      => esc_html( $this->action_data[ 'subtitle' ] ?? '' ),
				'not_logged_in' => __( 'Not currently logged-in.', 'wp-simple-firewall' ),
				'no_providers'  => __( 'There are currently no 2FA providers available on your account.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'provider_data' => ''
			],
		] );
	}
}