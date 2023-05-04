<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;

class IpAutoBlockCrowdsec extends IpBase {

	public const SLUG = 'ip_autoblock_crowdsec';
	public const WEIGHT = 6;

	protected function testIfProtected() :bool {
		/** @var Options $opts */
		$opts = $this->con()->getModule_IPs()->getOptions();
		return parent::testIfProtected() && $opts->isEnabledCrowdSecAutoBlock();
	}

	protected function getOptConfigKey() :string {
		return 'cs_block';
	}

	public function title() :string {
		return __( 'CrowdSec Community IP Blocking', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Crowd-Sourced IP Blocking with CrowdSec is switched ON', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( 'Crowd-Sourced IP Blocking with CrowdSec is switched OFF.', 'wp-simple-firewall' );
	}
}