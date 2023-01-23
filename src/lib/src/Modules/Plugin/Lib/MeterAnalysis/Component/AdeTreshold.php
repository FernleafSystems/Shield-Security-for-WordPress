<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class AdeTreshold extends AdeBase {

	public const SLUG = 'ade_threshold';

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