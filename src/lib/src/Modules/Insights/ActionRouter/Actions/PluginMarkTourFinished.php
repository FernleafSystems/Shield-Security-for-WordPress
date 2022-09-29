<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class PluginMarkTourFinished extends PluginBase {

	const SLUG = 'mark_tour_finished';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$mod->getTourManager()->setCompleted( Services::Request()->post( 'tour_key' ) );
		$this->response()->action_response_data = [
			'success' => true,
			'message' => 'Tour Finished'
		];
	}
}