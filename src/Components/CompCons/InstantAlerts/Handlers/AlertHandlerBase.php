<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts\Handlers;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts\EmailInstantAlertBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class AlertHandlerBase {

	use ExecOnce;
	use PluginControllerConsumer;

	/**
	 * @return class-string<EmailInstantAlertBase>
	 */
	abstract public function alertAction() :string;

	abstract public function alertTitle() :string;

	abstract public function alertDataKeys() :array;

	public function isImmediateAlert() :bool {
		return false;
	}

	/**
	 * Whether this alert's pending data must wait rather than be emailed now. Held data stays queued and goes out
	 * once this is false again.
	 */
	public function isSendHeld() :bool {
		return false;
	}
}