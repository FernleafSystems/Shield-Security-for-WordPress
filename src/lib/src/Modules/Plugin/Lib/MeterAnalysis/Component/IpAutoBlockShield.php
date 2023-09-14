<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;

class IpAutoBlockShield extends IpBase {

	public const SLUG = 'ip_autoblock_shield';
	public const WEIGHT = 7;

	protected function testIfProtected() :bool {
		/** @var Options $opts */
		$opts = self::con()->getModule_IPs()->opts();
		return parent::testIfProtected() && $opts->isEnabledAutoBlackList();
	}

	protected function getOptConfigKey() :string {
		return 'transgression_limit';
	}

	public function title() :string {
		return sprintf( __( '%s Intelligent IP Blocking', 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	public function descProtected() :string {
		$mod = self::con()->getModule_IPs();
		/** @var Options $opts */
		$opts = $mod->opts();
		return sprintf( __( 'Auto IP blocking is turned on with an offense limit of %s.', 'wp-simple-firewall' ),
			$opts->getOffenseLimit() );
	}

	public function descUnprotected() :string {
		return __( 'Auto IP blocking is switched-off as there is no offense limit provided.', 'wp-simple-firewall' );
	}
}