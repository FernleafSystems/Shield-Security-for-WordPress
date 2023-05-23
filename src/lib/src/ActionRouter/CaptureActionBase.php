<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CaptureActionBase {

	use ExecOnce;
	use PluginControllerConsumer;

	/**
	 * @var ?ActionResponse
	 */
	protected $actionResponse;

	protected function canRun() :bool {
		$req = Services::Request();
		return $req->request( ActionData::FIELD_ACTION ) === ActionData::FIELD_SHIELD
			   && !empty( $req->request( ActionData::FIELD_EXECUTE ) )
			   && \preg_match( '#^[a-z0-9_.:\-]+$#', $req->request( ActionData::FIELD_EXECUTE ) );
	}

	protected function extractActionSlug() :string {
		\preg_match( '#^([a-z0-9_.:\-]+)$#', Services::Request()->request( ActionData::FIELD_EXECUTE ), $matches );
		return $matches[ 1 ];
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