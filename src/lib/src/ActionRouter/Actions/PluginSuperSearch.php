<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SelectSearchData;

class PluginSuperSearch extends BaseAction {

	public const SLUG = 'super_search_select';

	protected function exec() {
		$this->response()->action_response_data = [
			'success' => true,
			'results' => ( new SelectSearchData() )->build( $this->action_data[ 'search' ] ?? '' ),
		];
	}
}