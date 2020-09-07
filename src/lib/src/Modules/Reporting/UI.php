<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

class UI extends Base\ShieldUI {

	public function buildInsightsVars() :array {
		$oEvtsMod = $this->getCon()->getModule_Events();
		/** @var Events\Strings $oStrs */
		$oStrs = $oEvtsMod->getStrings();
		$aEvtNames = $oStrs->getEventNames();

		return [
			'ajax'    => [
				'render_chart' => $oEvtsMod->getAjaxActionData( 'render_chart', true ),
			],
			'flags'   => [],
			'strings' => [
			],
			'vars'    => [
				'events_options' => array_intersect_key(
					$aEvtNames,
					array_flip(
						[
							'ip_offense',
							'conn_kill',
							'firewall_block',
						]
					)
				)
			],
		];
	}
}