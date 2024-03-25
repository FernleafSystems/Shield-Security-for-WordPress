<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\UserMfa;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AnyUserAuthRequired;
use FernleafSystems\Wordpress\Services\Services;

class ConfigForm extends UserMfaBase {

	use AnyUserAuthRequired;

	public const SLUG = 'user_mfa_config_form';
	public const TEMPLATE = '/user/profile/mfa/main.twig';

	protected function getDefaults() :array {
		return [
			'user_id'  => Services::WpUsers()->getCurrentWpUserId(),
			'title'    => __( 'Multi-Factor Authentication', 'wp-simple-firewall' ),
			'subtitle' => __( 'Provided by Shield', 'wp-simple-firewall' ),
		];
	}

	protected function getRenderData() :array {
		$WPU = Services::WpUsers();
		$user = $WPU->getUserById( $this->action_data[ 'user_id' ] ?? $WPU->getCurrentWpUserId() );

		$providerRenders = \array_map(
			function ( $provider ) {
				return $provider->renderUserProfileConfigFormField();
			},
			$user instanceof \WP_User ? self::con()->comps->mfa->getProvidersAvailableToUser( $user ) : []
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