<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\TourManager;
use FernleafSystems\Wordpress\Services\Services;

class PluginMarkTourFinished extends BaseAction {

	public const SLUG = 'mark_tour_finished';

	protected function exec() {
		( new TourManager() )->setCompleted( Services::Request()->post( 'tour_key' ) );
		$this->response()->action_response_data = [
			'success' => true,
			'message' => __( 'Tour Finished', 'wp-simple-firewall' ),
		];
	}
}