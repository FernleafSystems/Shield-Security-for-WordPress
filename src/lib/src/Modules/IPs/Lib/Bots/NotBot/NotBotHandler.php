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

	public const LIFETIME = 300;
	public const COOKIE_SLUG = 'notbot';
	public const SIGNAL_NOTBOT = 'notbot';
	public const SIGNAL_ALTCHA = 'altcha';

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
		$cookieParts = $this->getNonRequiredSignals();
		$cookieParts[] = 'exp-'.( Services::Request()->ts() + self::LIFETIME );
		Services::Response()->cookieSet(
			self::con()->prefix( self::COOKIE_SLUG ),
			\implode( 'Z', $cookieParts ),
			apply_filters( 'shield/notbot_cookie_life', self::LIFETIME )
		);
	}

	public function getNonRequiredSignals() :array {
		return \array_diff( $this->getSignalSlugs(), $this->getRequiredSignals() );
	}

	public function getRequiredSignals() :array {
		$con = self::con();
		try {
			$BS = ( new BotSignalsRecord() )
				->setIP( $con->this_req->ip )
				->retrieve();
		}
		catch ( \Exception $e ) {
			$BS = null;
		}
		return \array_keys( \array_filter( [
			self::SIGNAL_NOTBOT => !empty( $BS )
								   && $con->comps->altcha->complexityLevel() !== 'none'
								   && ( Services::Request()->ts() - $BS->notbot_at > HOUR_IN_SECONDS ),
			self::SIGNAL_ALTCHA => !empty( $BS )
								   && $con->comps->altcha->enabled()
								   && ( Services::Request()->ts() - $BS->altcha_at > HOUR_IN_SECONDS ),
		] ) );
	}

	protected function getSignalSlugs() :array {
		return [
			self::SIGNAL_NOTBOT,
			self::SIGNAL_ALTCHA,
		];
	}

	/**
	 * @deprecated 19.2.0
	 */
	public function hasCookie() :bool {
		return false;
	}

	/**
	 * @deprecated 19.2.0
	 */
	public function getHashForVisitorTS( int $ts ) {
		return \hash_hmac( 'sha1', $ts.self::con()->this_req->ip, ( new InstallationID() )->id() );
	}

	/**
	 * @deprecated 19.2.0
	 */
	public function sendNotBotNonceCookie() {
		Services::Response()->cookieSet( 'shield-notbot-nonce', ActionNonce::Create( CaptureNotBot::class ), 120 );
	}

	/**
	 * @deprecated 19.2.0
	 */
	public function getLastNotBotSignalAt() :int {
		return ( new BotSignalsRecord() )
			->setIP( self::con()->this_req->ip )
			->retrieve()
			->notbot_at;
	}
}