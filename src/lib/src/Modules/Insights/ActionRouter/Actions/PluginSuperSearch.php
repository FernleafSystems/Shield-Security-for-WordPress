<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SelectSearchData;
use FernleafSystems\Wordpress\Services\Services;

class PluginSuperSearch extends PluginBase {

	const SLUG = 'super_search_select';

	protected function exec() {
		$this->response()->action_response_data = [
			'success' => true,
			'results' => ( new SelectSearchData() )
				->setCon( $this->getCon() )
				->build( Services::Request()->request( 'search' ) ),
		];
	}
}