<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class IpAutoBlockCrowdsec extends IpBase {

	public const SLUG = 'ip_autoblock_crowdsec';
	public const WEIGHT = 6;

	protected function testIfProtected() :bool {
		return self::con()->comps->opts_lookup->enabledCrowdSecAutoBlock();
	}

	protected function getOptConfigKey() :string {
		return 'cs_block';
	}

	public function title() :string {
		return __( 'CrowdSec Community IP Blocking', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		$desc = __( 'Crowd-Sourced IP Blocking with CrowdSec is switched ON.', 'wp-simple-firewall' );
		if ( !self::con()->caps->canCrowdsecLevel2() ) {
			$desc .= ' '.__( 'Additional IP block lists are available with an upgraded plan.' );
		}
		return $desc;
	}

	public function descUnprotected() :string {
		return __( 'Crowd-Sourced IP Blocking with CrowdSec is switched OFF.', 'wp-simple-firewall' );
	}
}