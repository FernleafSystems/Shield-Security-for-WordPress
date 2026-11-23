<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\TourManager;

class PluginMarkTourFinished extends BaseAction {

	use Traits\SecurityAdminNotRequired;

	public const SLUG = 'mark_tour_finished';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$completed = ( new TourManager() )->setCompleted( $this->fields()->string( 'tour_key', '' ) );
		$this->response()->setPayload( [
			'message'   => __( 'Tour Finished', 'wp-simple-firewall' ),
			'completed' => $completed,
		] )->setPayloadSuccess( true );
	}
}
