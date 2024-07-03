<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class IpAdeThreshold extends IpBase {

	public const SLUG = 'ip_ade_threshold';

	protected function testIfProtected() :bool {
		return self::con()->comps->opts_lookup->getAntiBotMinScore() > 0;
	}

	protected function getOptConfigKey() :string {
		return 'antibot_minimum';
	}

	public function title() :string {
		return __( 'silentCAPTCHA', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'silentCAPTCHA is enabled with a minimum bot-score threshold.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "silentCAPTCHA is disabled as there is no minimum bot-score threshold provided.", 'wp-simple-firewall' );
	}
}