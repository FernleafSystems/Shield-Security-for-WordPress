<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;

class IpAddressSource extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'ip_address_source';
	public const WEIGHT = 5;

	protected function testIfProtected() :bool {
		/** @var Options $opts */
		$opts = self::con()->getModule_Plugin()->opts();
		return $opts->getIpSource() === 'REMOTE_ADDR';
	}

	protected function getOptConfigKey() :string {
		return 'visitor_address_source';
	}

	public function title() :string {
		return __( 'Visitor IP Source', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "Visitor IP address can't be spoofed because the IP source is set to 'REMOTE_ADDR'.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Visitors IP address can be spoofed because the IP source isn't set to 'REMOTE_ADDR'.", 'wp-simple-firewall' );
	}
}