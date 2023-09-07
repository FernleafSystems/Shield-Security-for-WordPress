<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;

class IpAutoBlockOffenseLimit extends IpBase {

	public const SLUG = 'ip_autoblock_limit';

	protected function testIfProtected() :bool {
		/** @var Options $opts */
		$opts = self::con()->getModule_IPs()->opts();
		return parent::testIfProtected() && $opts->isEnabledAutoBlackList() && $opts->getOffenseLimit() <= 10;
	}

	protected function getOptConfigKey() :string {
		return 'transgression_limit';
	}

	public function title() :string {
		return __( 'IP Auto-Block Offense Limit', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		/** @var Options $opts */
		$opts = self::con()->getModule_IPs()->opts();
		return sprintf( __( "The maximum allowable offenses allowed before blocking is reasonably low: %s", 'wp-simple-firewall' ),
			$opts->getOffenseLimit() );
	}

	public function descUnprotected() :string {
		/** @var Options $opts */
		$opts = self::con()->getModule_IPs()->opts();
		return sprintf( __( "Your maximum offense limit before blocking an IP seems high: %s", 'wp-simple-firewall' ),
			$opts->getOffenseLimit() );
	}
}