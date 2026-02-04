<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class HeadersCsp extends Base {

	public function title() :string {
		return __( 'Advanced Content Security Policy (CSP) Headers', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Configure advanced CSP headers to provide granular control over your site content and how assets are served.', 'wp-simple-firewall' );
	}

	protected function tooltip() :string {
		return __( 'Add custom CSP headers', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();
		$status[ 'exp' ][] = __( "Applying CSP headers may affect how your site functions for its visitors, so we don't offer any direct recommendations in this section.", 'wp-simple-firewall' );
		return $status;
	}
}