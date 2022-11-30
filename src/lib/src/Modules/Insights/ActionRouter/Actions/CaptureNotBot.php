<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;

class CaptureNotBot extends IpsBase {

	use Traits\AuthNotRequired;

	public const SLUG = 'not_bot';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$this->response()->success = $mod->getBotSignalsController()
										 ->getHandlerNotBot()
										 ->registerAsNotBot();
	}
}