<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ImportExportSites;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildSearchPanesData;

class BuildSearchPanesData extends BaseBuildSearchPanesData {

	/**
	 * @return array{
	 *   options:array{
	 *     sync_state:list<array{label:string,value:string}>,
	 *     status_key:list<array{label:string,value:string}>,
	 *     queue_status_key:list<array{label:string,value:string}>
	 *   }
	 * }
	 */
	public function build() :array {
		$statusBuilder = new SiteSyncStatusBuilder();

		return [
			'options' => [
				'sync_state'       => $statusBuilder->stateSearchPaneOptions(),
				'status_key'       => $statusBuilder->registrationSearchPaneOptions(),
				'queue_status_key' => $statusBuilder->queueSearchPaneOptions(),
			]
		];
	}
}
