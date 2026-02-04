<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class HeadersGeneral extends Base {

	public function title() :string {
		return __( 'Basic HTTP Headers', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Configure some basic HTTP Headers to protect your visitors and site content.', 'wp-simple-firewall' );
	}

	protected function tooltip() :string {
		return __( 'Adjust deployment of HTTP security headers', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();
		$status[ 'exp' ][] = __( "Certain headers may affect how your site functions for its visitors, so we don't offer any direct recommendations in this section.", 'wp-simple-firewall' );
		return $status;
	}
}