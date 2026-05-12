<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\TourManager;

class PluginMarkTourFinished extends BaseAction {

	use Traits\SecurityAdminNotRequired;

	public const SLUG = 'mark_tour_finished';

	protected function exec() {
		$completed = ( new TourManager() )->setCompleted( $this->action_data[ 'tour_key' ] ?? '' );
		$this->response()->setPayload( [
			'message'   => __( 'Tour Finished', 'wp-simple-firewall' ),
			'completed' => $completed,
		] )->setPayloadSuccess( true );
	}
}
