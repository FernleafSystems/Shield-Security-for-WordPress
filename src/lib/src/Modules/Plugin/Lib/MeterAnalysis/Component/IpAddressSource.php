<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;

class IpAddressSource extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'ip_address_source';
	public const WEIGHT = 5;

	protected function testIfProtected() :bool {
		return \in_array( $this->source(), [ 'REMOTE_ADDR', 'HTTP_CF_CONNECTING_IP' ] );
	}

	protected function getOptConfigKey() :string {
		return 'visitor_address_source';
	}

	public function title() :string {
		return __( 'Visitor IP Source', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		if ( $this->source() === 'REMOTE_ADDR' ) {
			$msg = __( "Visitor IP address can't be spoofed because the IP source is set to 'REMOTE_ADDR'.", 'wp-simple-firewall' );
		}
		else {
			$msg = \implode( ' ', [
				__( 'Visitor IP address is determine by CloudFlare.', 'wp-simple-firewall' ),
				__( 'Always ensure CloudFlare is set to be a proxy for your site.', 'wp-simple-firewall' ),
			] );
		}
		return $msg;
	}

	public function descUnprotected() :string {
		return __( "Visitors IP address can be spoofed because the IP source isn't set to 'REMOTE_ADDR'.", 'wp-simple-firewall' );
	}

	private function source() :string {
		return Services::Request()->getIpDetector()->getPublicRequestSource();
	}
}