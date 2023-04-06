<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock\BaseAutoUnblock;
use FernleafSystems\Wordpress\Services\Services;

abstract class IpAutoUnblockBase extends BaseAction {

	use Traits\ByPassIpBlock;

	protected function exec() {
		$unblockerClass = $this->getAutoUnblockerClass();
		/** @var BaseAutoUnblock $unBlocker */
		$unBlocker = new $unblockerClass();
		if ( $unBlocker->canRunAutoUnblockProcess() && $unBlocker->processAutoUnblockRequest() ) {
			Services::Response()->redirectToHome();
		}
		$this->response()->success = false;
	}

	abstract protected function getAutoUnblockerClass() :string;
}