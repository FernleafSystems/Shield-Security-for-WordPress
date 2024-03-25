<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;

class CaptureNotBot extends BaseAction {

	use Traits\AuthNotRequired;

	public const SLUG = 'capture_not_bot';

	protected function exec() {
		$notBotHandler = self::con()->comps->not_bot;

		$cookieLife = apply_filters( 'shield/notbot_cookie_life', $notBotHandler::LIFETIME );
		$ts = Services::Request()->ts() + $cookieLife;
		Services::Response()->cookieSet(
			self::con()->prefix( $notBotHandler::COOKIE_SLUG ),
			sprintf( '%sz%s', $ts, $notBotHandler->getHashForVisitorTS( $ts ) ),
			$cookieLife
		);

		self::con()->fireEvent( 'bottrack_notbot' );

		$this->response()->success = true;
	}

	public static function NonceCfg() :array {
		return [
			'ip'  => false,
			'ttl' => 24,
		];
	}
}