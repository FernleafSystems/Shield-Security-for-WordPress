<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
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
			$req->query,
			$req->post
		);
	}

	protected function isRunnableShieldTransport( array $transport ) :bool {
		return ActionData::TransportField( $transport, ActionData::FIELD_ACTION ) === ActionData::FIELD_SHIELD
			   && ( new ExternalEntryGuard() )->admit( $this->requestedSlug( $transport ), $this->guardedEntry() );
	}

	/**
	 * The ExternalEntryGuard entry this capture enforces. Without one, nothing is admitted.
	 */
	protected function guardedEntry() :string {
		return '';
	}

	/**
	 * The slug of the action the request names, as the request gave it. The guard decides what it means.
	 */
	protected function requestedSlug( array $transport ) :string {
		return ActionData::TransportField( $transport, ActionData::FIELD_EXECUTE );
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
