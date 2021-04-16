<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Profiles;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\MfaControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpUserConsumer;

class RenderCustomForms {

	use MfaControllerConsumer;
	use WpUserConsumer;

	private $attributes;

	public function render( array $attributes ) :string {
		$this->attributes = $attributes;
		return $this->getMfaCon()
					->getMod()
					->renderTemplate( '/user/profile/mfa/main.twig', $this->buildRenderData() );
	}

	private function buildRenderData() :array {
		$mfaCon = $this->getMfaCon();
		$con = $mfaCon->getCon();
		$pluginName = $con->getHumanName();

		$user = $this->getWpUser();
		$providers = $user instanceof \WP_User ? $mfaCon->getProvidersForUser( $user ) : [];
		$providerRenders = $user instanceof \WP_User ?
			array_map( function ( $provider ) {
				return $provider->renderUserProfileCustomForm( $this->getWpUser() );
			}, $providers )
			: [];

		$data = [
			'content' => [
				'providers' => $providerRenders,
			],
			'flags'   => [
				'has_providers' => !empty( $providers ),
				'logged_in'     => $user instanceof \WP_User,
			],
			'strings' => [
				'title'         => sprintf( __( '%s MFA Options', 'wp-simple-firewall' ), $pluginName ),
				'not_logged_in' => __( 'Not currently logged-in.', 'wp-simple-firewall' ),
				'no_providers'  => __( 'There are currently no 2FA providers available on your account.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'provider_data' => ''
			],
		];

		return apply_filters( 'shield/render_data_custom_profiles_mfa', $data );
	}
}