<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;

class IpAdeThreshold extends IpBase {

	public const SLUG = 'ip_ade_threshold';

	protected function testIfProtected() :bool {
		/** @var Options $opts */
		$opts = self::con()->getModule_IPs()->getOptions();
		return parent::testIfProtected() && $opts->getAntiBotMinimum() > 0;
	}

	protected function getOptConfigKey() :string {
		return 'antibot_minimum';
	}

	public function title() :string {
		return __( 'AntiBot Detection Engine', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'AntiBot Detection Engine is enabled with a minimum bot-score threshold.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "AntiBot Detection Engine is disabled as there is no minimum bot-score threshold provided.", 'wp-simple-firewall' );
	}
}