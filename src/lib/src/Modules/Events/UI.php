<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class UI extends BaseShield\UI {

	public function buildInsightsVars() :array {
		return [
			'flags'   => [],
			'strings' => [],
			'vars'    => [
				'stats'          => $this->buildStats(),
				'stat_intervals' => [
					'days_1'   => '24 Hours',
					'days_7'   => '7 Days',
					'months_1' => '1 Month',
					'lifetime' => 'Lifetime',
				]
			],
		];
	}

	private function buildStats() :array {
		$stats = [];

		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Strings $strings */
		$strings = $mod->getStrings();
		foreach ( $this->getAllEvents() as $event ) {
			$sums = $this->buildSums( $event );
			if ( !empty( array_filter( $sums ) ) ) {
				$stats[ $event ] = [
					'key'    => $event,
					'name'   => $strings->getEventName( $event ),
					'counts' => $this->buildSums( $event ),
				];
			}
		}

		return $stats;
	}

	private function buildSums( $event ) :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Databases\Events\Select $selector */
		$selector = $mod->getDbHandler_Events()->getQuerySelector();
		$carbon = Services::Request()->carbon( true );
		return [
			'lifetime' => $selector->sumEvent( $event ),
			'months_1' => $selector
				->filterByCreatedAt( ( clone $carbon )->subDays( 30 )->startOfDay()->timestamp, '>' )
				->sumEvent( $event ),
			'days_7'   => $selector
				->filterByCreatedAt( ( clone $carbon )->subDays( 7 )->startOfDay()->timestamp, '>' )
				->sumEvent( $event ),
			'days_1'   => $selector
				->filterByCreatedAt( ( clone $carbon )->subHours( 24 )->timestamp, '>' )
				->sumEvent( $event ),
		];
	}

	private function getAllEvents() :array {
		return [
			'ip_offense',
			'conn_kill',
			'bottrack_404',
			'bottrack_fakewebcrawler',
			'bottrack_linkcheese',
			'bottrack_loginfailed',
			'bottrack_logininvalid',
			'bottrack_useragent',
			'bottrack_xmlrpc',
			'bottrack_invalidscript',
		];
	}
}