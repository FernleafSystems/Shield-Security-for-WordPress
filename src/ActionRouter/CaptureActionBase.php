<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\{
	ExternalActionTransportPolicy,
	ExternalEntryGuard
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CaptureActionBase {

	use ExecOnce;
	use PluginControllerConsumer;

	/**
	 * @var ?RoutedResponse
	 */
	protected ?RoutedResponse $actionResponse = null;

	protected function canRun() :bool {
		return $this->isRunnableShieldTransport( $this->transportData() );
	}

	protected function transportData() :array {
		$req = Services::Request();
		return \array_merge(
			\is_array( $req->query ) ? $req->query : [],
			\is_array( $req->post ) ? $req->post : []
		);
	}

	protected function isRunnableShieldTransport( array $transport ) :bool {
		$action = (string)( $transport[ ActionData::FIELD_ACTION ] ?? '' );
		$slug = (string)( $transport[ ActionData::FIELD_EXECUTE ] ?? '' );

		return $action === ActionData::FIELD_SHIELD
			   && $slug !== ''
			   && ActionData::isValidActionSlug( $slug )
			   && $this->isAdmittedByGuard( $slug )
			   && ( new ExternalActionTransportPolicy() )->isAllowed( $slug, $transport, $this->actionType() );
	}

	private function isAdmittedByGuard( string $slug ) :bool {
		$entry = $this->guardedEntry();
		if ( $entry === '' ) {
			return true;
		}
		$guard = new ExternalEntryGuard();
		$admitted = $guard->isAdmitted( $slug, $entry );
		if ( !$admitted ) {
			$guard->recordRejection( $slug, $entry );
		}
		return $admitted;
	}

	protected function actionType() :int {
		return ActionRoutingController::ACTION_SHIELD;
	}

	/**
	 * The ExternalEntryGuard entry this capture enforces. Empty for captures not yet on the guard.
	 */
	protected function guardedEntry() :string {
		return '';
	}

	protected function extractActionSlugFromTransport( array $transport ) :string {
		return ActionData::extractActionSlug( (string)( $transport[ ActionData::FIELD_EXECUTE ] ?? '' ) );
	}

	protected function run() {
		$this->preRun();
		$this->theRun();
		$this->postRun();
	}

	protected function preRun() {
	}

	protected function theRun() {
	}

	protected function postRun() {
	}
}
