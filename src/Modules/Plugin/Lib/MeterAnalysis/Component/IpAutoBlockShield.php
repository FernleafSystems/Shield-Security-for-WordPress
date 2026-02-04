<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class IpAutoBlockShield extends IpBase {

	public const SLUG = 'ip_autoblock_shield';
	public const WEIGHT = 7;

	protected function testIfProtected() :bool {
		return self::con()->comps->opts_lookup->enabledIpAutoBlock();
	}

	protected function getOptConfigKey() :string {
		return 'transgression_limit';
	}

	public function title() :string {
		return sprintf( __( '%s Intelligent IP Blocking', 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	public function descProtected() :string {
		return sprintf( __( 'Auto IP blocking is turned on with an offense limit of %s.', 'wp-simple-firewall' ),
			self::con()->comps->opts_lookup->getIpAutoBlockOffenseLimit() );
	}

	public function descUnprotected() :string {
		return __( 'Auto IP blocking is switched-off as there is no offense limit provided.', 'wp-simple-firewall' );
	}
}