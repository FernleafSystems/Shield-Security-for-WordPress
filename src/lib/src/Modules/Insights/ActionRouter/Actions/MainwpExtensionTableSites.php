<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

class MainwpExtensionTableSites extends MainwpBase {

	const SLUG = 'mainwp_ext_table';

	protected function exec() {
		$this->response()->action_response_data = [
			'success'     => true,
		];
	}
}