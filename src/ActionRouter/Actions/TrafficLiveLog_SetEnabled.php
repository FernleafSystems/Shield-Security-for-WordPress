<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;

class TrafficLiveLog_SetEnabled extends BaseAction {

	use Traits\SecurityAdminRequired;

	public const SLUG = 'traffic_live_log_set_enabled';

	protected function exec() {
		$enabled = (string)( $this->action_data[ 'enabled' ] ?? '' );
		if ( !\in_array( $enabled, [ 'Y', 'N' ], true ) ) {
			$this->respond(
				false,
				__( 'Invalid live traffic logging state.', 'wp-simple-firewall' ),
				false,
				self::con()->comps->opts_lookup->peekTrafficLiveLogTimeRemaining()
			);
			return;
		}

		if ( !self::con()->caps->canTrafficLiveLog() ) {
			$this->respond(
				false,
				__( 'Live traffic logging is not available for this site.', 'wp-simple-firewall' ),
				false,
				self::con()->comps->opts_lookup->peekTrafficLiveLogTimeRemaining()
			);
			return;
		}

		$opts = self::con()->opts;
		if ( $enabled === 'Y' ) {
			$opts->optSet( 'enable_live_log', 'Y' )
				 ->optSet( 'live_log_started_at', Services::Request()->ts() );
			$message = __( 'Live traffic logging has been enabled. Reloading...', 'wp-simple-firewall' );
		}
		else {
			$opts->optSet( 'enable_live_log', 'N' )
				 ->optSet( 'live_log_started_at', 0 );
			$message = __( 'Live traffic logging has been disabled. Reloading...', 'wp-simple-firewall' );
		}
		$opts->store();

		$this->respond(
			true,
			$message,
			true,
			self::con()->comps->opts_lookup->getTrafficLiveLogTimeRemaining()
		);
	}

	private function respond( bool $success, string $message, bool $pageReload, int $timeRemaining ) :void {
		$this->response()->setPayload( [
			'message'        => $message,
			'page_reload'    => $pageReload,
			'is_enabled'     => $timeRemaining > 0,
			'time_remaining' => $timeRemaining,
		] )->setPayloadSuccess( $success );
	}
}
