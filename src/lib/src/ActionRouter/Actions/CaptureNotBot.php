<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;

class CaptureNotBot extends BaseAction {

	use Traits\AuthNotRequired;

	public const SLUG = 'capture_not_bot';

	protected function exec() {
		$notBotHandler = $this->con()
							  ->getModule_IPs()
							  ->getBotSignalsController()
							  ->getHandlerNotBot();

		$cookieLife = apply_filters( 'shield/notbot_cookie_life', $notBotHandler::LIFETIME );
		$ts = Services::Request()->ts() + $cookieLife;
		Services::Response()->cookieSet(
			$this->con()->prefix( $notBotHandler::SLUG ),
			sprintf( '%sz%s', $ts, $notBotHandler->getHashForVisitorTS( $ts ) ),
			$cookieLife
		);

		$this->con()->fireEvent( 'bottrack_notbot' );

		$this->response()->success = true;
	}
}