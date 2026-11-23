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

	/**
	 * What of a queued alert still needs sending, or an empty array when nothing remains. An alert held back for a
	 * while can be about something that no longer calls for one by the time it goes.
	 */
	public function alertDataStillToSend( array $alertGroupData ) :array {
		return $alertGroupData;
	}

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