<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\UI\BuildDataForStats;

class UI extends BaseShield\UI {

	public function buildInsightsVars() :array {

		$statsVars = ( new BuildDataForStats() )
			->setMod( $this->getMod() )
			->build();

		return [
			'flags'   => [
				'has_stats' => !empty( $statsVars[ 'stats' ] )
			],
			'strings' => [
				'no_stats' => __( 'No stats yet. It wont take long though, so check back here soon.' )
			],
			'vars'    => $statsVars,
		];
	}
}