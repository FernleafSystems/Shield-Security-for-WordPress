<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\InvalidActionNonceException;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Services\Services;

class CapturePluginAction extends CaptureActionBase {

	protected function canRun() :bool {
		return !self::con()->this_req->wp_is_ajax && parent::canRun();
	}

	protected function guardedEntry() :string {
		return ExternalEntryGuard::ENTRY_ORDINARY;
	}

	protected function theRun() {
		$transport = $this->transportData();
		$slug = $this->extractActionSlugFromTransport( $transport );

		$guard = new ExternalEntryGuard();
		try {
			$guard->verifyProof( $slug, $this->guardedEntry(), $transport );
			$this->actionResponse = self::con()->action_router->action( $slug, $transport );
		}
		catch ( InvalidActionNonceException $e ) {
			$guard->recordRejection( $slug, $this->guardedEntry() );
			ActionExecutor::DieInvalidNonce();
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}
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