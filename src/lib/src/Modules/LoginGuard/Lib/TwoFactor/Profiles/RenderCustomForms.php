<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Profiles;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\MfaControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpUserConsumer;

class RenderCustomForms {

	use MfaControllerConsumer;
	use WpUserConsumer;

	private $attributes;

	public function render( array $attributes ) :string {
		$this->setAttributes( $attributes );
		return $this->getMfaCon()
					->getMod()
					->renderTemplate( '/user/profile/mfa/main.twig', $this->buildRenderData() );
	}

	public function setAttributes( array $attributes ) {
		$con = $this->getMfaCon()->getCon();
		$this->attributes = shortcode_atts(
			[
				'title'    => sprintf( __( '%s MFA Options', 'wp-simple-firewall' ), $con->getHumanName() ),
				'subtitle' => '',
			],
			$attributes
		);
	}

	private function buildRenderData() :array {
		$mfaCon = $this->getMfaCon();
		$user = $this->getWpUser();
		$providers = $user instanceof \WP_User ? $mfaCon->getProvidersForUser( $user ) : [];
		$providerRenders = $user instanceof \WP_User ?
			array_map( function ( $provider ) {
				return $provider->renderUserProfileCustomForm( $this->getWpUser() );
			}, $providers )
			: [];

		return apply_filters( 'shield/render_data_custom_profiles_mfa', [
			'content' => [
				'providers' => $providerRenders,
			],
			'flags'   => [
				'has_providers' => !empty( $providers ),
				'logged_in'     => $user instanceof \WP_User,
			],
			'strings' => [
				'title'         => esc_html( $this->attributes[ 'title' ] ),
				'subtitle'      => esc_html( $this->attributes[ 'subtitle' ] ),
				'not_logged_in' => __( 'Not currently logged-in.', 'wp-simple-firewall' ),
				'no_providers'  => __( 'There are currently no 2FA providers available on your account.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'provider_data' => ''
			],
		] );
	}
}