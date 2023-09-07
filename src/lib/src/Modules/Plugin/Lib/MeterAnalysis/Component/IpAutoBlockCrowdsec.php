<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;

class IpAutoBlockCrowdsec extends IpBase {

	public const SLUG = 'ip_autoblock_crowdsec';
	public const WEIGHT = 6;

	protected function testIfProtected() :bool {
		/** @var Options $opts */
		$opts = self::con()->getModule_IPs()->opts();
		return parent::testIfProtected() && $opts->isEnabledCrowdSecAutoBlock();
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

	protected function score() :int {
		return $this->testIfProtected() ?
			( self::con()->caps->canCrowdsecLevel2() ? static::WEIGHT : static::WEIGHT/3 )
			: 0;
	}
}