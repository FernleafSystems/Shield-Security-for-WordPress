<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter\MeterOverallConfig;

class AllComponents extends Base {

	public const SLUG = 'all_components';
	public const WEIGHT = 80;

	protected function testIfProtected() :bool {
		return $this->score() > ( static::WEIGHT*0.8 );
	}

	public function score() :int {
		return (int)round(
			( new Handler() )->getMeter( MeterOverallConfig::class )[ 'totals' ][ 'percentage' ]*static::WEIGHT/100
		);
	}

	public function title() :string {
		return sprintf( __( 'Overall %s Plugin Configuration', 'wp-simple-firewall' ),
			$this->con()->getHumanName() );
	}

	public function descProtected() :string {
		return sprintf( __( "You've configured the %s plugin to protect your site to a high level.", 'wp-simple-firewall' ),
			$this->con()->getHumanName() );
	}

	public function descUnprotected() :string {
		return sprintf( __( 'There is room for improvement in your %s plugin configuration.', 'wp-simple-firewall' ),
			$this->con()->getHumanName() );
	}
}