<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\{
	ActionException,
	InvalidActionNonceException
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter\ResponseAdapterFactory;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Services\Services;

class CapturePluginAction extends CaptureActionBase {

	public const WP_DIE_INVALID_NONCE_CODE = 'shield_action_invalid_nonce';
	public const WP_DIE_INVALID_NONCE_STATUS = 400;

	protected function canRun() :bool {
		return !self::con()->this_req->wp_is_ajax && parent::canRun();
	}

	protected function guardedEntry() :string {
		return ExternalEntryGuard::ENTRY_ORDINARY;
	}

	protected function theRun() {
		$transport = $this->transportData();
		$slug = $this->requestedSlug( $transport );

		try {
			// The ordinary route merges query and POST for routing, but a form is the POST body.
			$this->actionResponse = ( new ResponseAdapterFactory() )
				->forActionType( ActionRoutingController::ACTION_SHIELD )
				->adapt( ( new ExternalEntryGuard() )->dispatch(
					$this->guardedEntry(),
					$slug,
					$transport,
					Services::Request()->post
				) );
		}
		catch ( InvalidActionNonceException $e ) {
			$this->dieInvalidNonce();
		}
		catch ( ActionException $e ) {
			// The action refused the request: not permitted, or its data is missing or invalid. That's an
			// outcome anyone can cause, not a fault, so it isn't logged.
		}
		catch ( \Throwable $e ) {
			// A fault in the action, not a refusal.
			error_log( \sprintf( 'Shield action %s failed: %s', $slug, $e->getMessage() ) );
		}
	}

	/**
	 * @return never
	 */
	private function dieInvalidNonce() :void {
		wp_die(
			__( 'Unexpected data. Please try again.', 'wp-simple-firewall' ),
			'',
			[
				'code'     => self::WP_DIE_INVALID_NONCE_CODE,
				'response' => self::WP_DIE_INVALID_NONCE_STATUS,
			]
		);
	}

	protected function postRun() {
		if ( empty( $this->actionResponse ) ) {
			return;
		}

		$payload = $this->actionResponse->payload();
		if ( isset( $payload[ 'next_step' ][ 'type' ] ) ) {
			switch ( $payload[ 'next_step' ][ 'type' ] ) {
				case 'redirect':
					$url = $payload[ 'next_step' ][ 'url' ] ?? '';
					Services::Response()->redirect( empty( $url ) ? Services::WpGeneral()->getHomeUrl() : $url );
					break;
				default:
					break;
			}
		}
	}
}