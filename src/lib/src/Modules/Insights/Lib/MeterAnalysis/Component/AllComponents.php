<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Meter\MeterOverallConfig;

class AllComponents extends Base {

	public const SLUG = 'all_components';
	public const WEIGHT = 150;

	protected function isProtected() :bool {
		return $this->score() > 85;
	}

	public function score() :?int {
		return ( new Handler() )
				   ->setCon( $this->getCon() )
				   ->getMeter( MeterOverallConfig::class )[ 'totals' ][ 'percentage' ];
	}

	public function title() :string {
		return sprintf( __( 'Overall %s Plugin Configuration', 'wp-simple-firewall' ),
			$this->getCon()->getHumanName() );
	}

	public function descProtected() :string {
		return sprintf( __( 'Your %s plugin is configured to protect your site to a high level.', 'wp-simple-firewall' ),
			$this->getCon()->getHumanName() );
	}

	public function descUnprotected() :string {
		return sprintf( __( 'There is room for improvement in your %s plugin configuration.', 'wp-simple-firewall' ),
			$this->getCon()->getHumanName() );
	}
}