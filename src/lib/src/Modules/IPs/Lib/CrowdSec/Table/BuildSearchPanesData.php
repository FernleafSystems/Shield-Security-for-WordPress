<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\CrowdSecDecisions\LoadCrowdsecDecisions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class BuildSearchPanesData {

	use ModConsumer;

	public function build() :array {
		return [
			'options' => [
				'ip'      => $this->buildForIPs(),
			]
		];
	}

	private function buildForIPs() :array {
		return array_map(
			function ( $ip ) {
				return [
					'label' => $ip,
					'value' => $ip,
				];
			},
			( new LoadCrowdsecDecisions() )
				->setMod( $this->getCon()->getModule_IPs() )
				->getDistinctIPs()
		);
	}
}