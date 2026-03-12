<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\ActionsQueueScanStateBuilder;

class ActionsQueueScanRailMetricsBuilder {

	/**
	 * @return array{
	 *   tabs:array<string,array{count:int,status:string}>,
	 *   rail_accent_status:string
	 * }
	 */
	public function build() :array {
		$state = ( new ActionsQueueScanStateBuilder() )->build();

		return [
			'tabs'               => $state[ 'tabs' ],
			'rail_accent_status' => $state[ 'rail_accent_status' ],
		];
	}
}
