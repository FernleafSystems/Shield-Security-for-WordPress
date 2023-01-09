<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;

class IpAutoBlockCrowdsec extends IpBase {

	public const SLUG = 'ip_autoblock_crowdsec';
	public const WEIGHT = 50;

	protected function isProtected() :bool {
		/** @var Options $opts */
		$opts = $this->getCon()->getModule_IPs()->getOptions();
		return parent::isProtected() && $opts->isEnabledCrowdSecAutoBlock();
	}

	public function href() :string {
		return $this->getCon()->getModule_IPs()->isModOptEnabled() ?
			$this->link( 'cs_block' ) : parent::href();
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