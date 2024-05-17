<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionNonce,
	Actions\CaptureNotBot
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\InstallationID;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class NotBotHandler {

	use ExecOnce;
	use PluginControllerConsumer;

	public const LIFETIME = 600;
	public const COOKIE_SLUG = 'notbot';

	protected function canRun() :bool {
		return (bool)apply_filters( 'shield/can_run_antibot', true );
	}

	protected function run() {
		if ( \defined( 'LOGGED_IN_COOKIE' ) && Services::Request()->cookie( LOGGED_IN_COOKIE ) ) {
			add_action( 'init', [ $this, 'sendNotBotFlagCookie' ] );
		}
		else {
			$this->sendNotBotFlagCookie();
		}

		( new InsertNotBotJs() )->execute();
	}

	public function sendNotBotFlagCookie() {
		Services::Response()->cookieSet(
			self::con()->prefix( self::COOKIE_SLUG ),
			\implode( 'Z', $this->getNonRequiredSignals() ),
			apply_filters( 'shield/notbot_cookie_life', self::LIFETIME )
		);
	}

	public function getNonRequiredSignals() :array {
		$BS = ( new BotSignalsRecord() )
			->setIP( self::con()->this_req->ip )
			->retrieve();
		return \array_keys( \array_filter( [
			'notbot' => Services::Request()->ts() - $BS->notbot_at < HOUR_IN_SECONDS,
			'altcha' => Services::Request()->ts() - $BS->altcha_at < HOUR_IN_SECONDS,
		] ) );
	}

	/**
	 * @deprecated 19.1.14
	 */
	public function hasCookie() :bool {
		return false;
	}

	/**
	 * @deprecated 19.1.14
	 */
	public function getHashForVisitorTS( int $ts ) {
		return \hash_hmac( 'sha1', $ts.self::con()->this_req->ip, ( new InstallationID() )->id() );
	}

	/**
	 * @deprecated 19.1.14
	 */
	public function sendNotBotNonceCookie() {
		Services::Response()->cookieSet( 'shield-notbot-nonce', ActionNonce::Create( CaptureNotBot::class ), 120 );
	}

	/**
	 * @deprecated 19.1.14
	 */
	public function getLastNotBotSignalAt() :int {
		return ( new BotSignalsRecord() )
			->setIP( self::con()->this_req->ip )
			->retrieve()
			->notbot_at;
	}
}