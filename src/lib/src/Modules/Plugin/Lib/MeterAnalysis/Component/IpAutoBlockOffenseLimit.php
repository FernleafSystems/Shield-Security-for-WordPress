<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;

class IpAutoBlockOffenseLimit extends IpBase {

	public const SLUG = 'ip_autoblock_limit';

	protected function isProtected() :bool {
		/** @var Options $opts */
		$opts = $this->getCon()->getModule_IPs()->getOptions();
		return parent::isProtected() && $opts->isEnabledAutoBlackList() && $opts->getOffenseLimit() <= 10;
	}

	public function href() :string {
		return $this->getCon()->getModule_IPs()->isModOptEnabled() ?
			$this->link( 'transgression_limit' ) : parent::href();
	}

	public function title() :string {
		return __( 'IP Auto-Block Offense Limit', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		/** @var Options $opts */
		$opts = $this->getCon()->getModule_IPs()->getOptions();
		return sprintf( __( "The maximum allowable offenses allowed before blocking is reasonably low: %s", 'wp-simple-firewall' ),
			$opts->getOffenseLimit() );
	}

	public function descUnprotected() :string {
		/** @var Options $opts */
		$opts = $this->getCon()->getModule_IPs()->getOptions();
		return sprintf( __( "Your maximum offense limit before blocking an IP seems high: %s", 'wp-simple-firewall' ),
			$opts->getOffenseLimit() );
	}
}