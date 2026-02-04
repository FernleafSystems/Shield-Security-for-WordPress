<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class IpAutoBlockOffenseLimit extends IpBase {

	public const SLUG = 'ip_autoblock_limit';

	protected function testIfProtected() :bool {
		$opts = self::con()->comps->opts_lookup;
		return $opts->enabledIpAutoBlock() && $opts->getIpAutoBlockOffenseLimit() <= 20;
	}

	protected function getOptConfigKey() :string {
		return 'transgression_limit';
	}

	public function title() :string {
		return __( 'IP Auto-Block Offense Limit', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return sprintf( __( "The maximum allowable offenses allowed before blocking is reasonable: %s", 'wp-simple-firewall' ),
			self::con()->comps->opts_lookup->getIpAutoBlockOffenseLimit() );
	}

	public function descUnprotected() :string {
		return sprintf( __( "Your maximum offense limit before blocking an IP seems high: %s", 'wp-simple-firewall' ),
			self::con()->comps->opts_lookup->getIpAutoBlockOffenseLimit() );
	}
}