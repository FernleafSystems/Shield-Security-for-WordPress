<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\CaptureNotBot,
	ActionNonce
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\InstallationID;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class NotBotHandler {

	use ExecOnce;
	use ModConsumer;

	public const LIFETIME = 600;
	public const SLUG = 'notbot';

	private $previousNotBotSignalAt = null;

	protected function canRun() :bool {
		return (bool)apply_filters( 'shield/can_run_antibot', true );
	}

	protected function run() {
		if ( \defined( 'LOGGED_IN_COOKIE' ) && Services::Request()->cookie( LOGGED_IN_COOKIE ) ) {
			add_action( 'init', [ $this, 'sendNotBotNonceCookie' ] );
		}
		else {
			$this->sendNotBotNonceCookie();
		}

		( new InsertNotBotJs() )->execute();
	}

	public function getLastNotBotSignalAt() :int {
		if ( $this->previousNotBotSignalAt === null ) {
			$this->previousNotBotSignalAt = ( new BotSignalsRecord() )
				->setIP( self::con()->this_req->ip )
				->retrieveNotBotAt();
		}
		return $this->previousNotBotSignalAt;
	}

	/**
	 * Hooked to "setup_theme" (at least) as ActionData::Build() requires $wp_rewrite to be initialised,
	 * and this is the earliest we can hook into.
	 */
	public function sendNotBotNonceCookie() {
		Services::Response()->cookieSet( 'shield-notbot-nonce', ActionNonce::Create( CaptureNotBot::class ), 120 );
	}

	public function hasCookie() :bool {
		$cookie = [];
		$req = Services::Request();
		$notBot = $req->cookie( self::con()->prefix( self::SLUG ), '' );
		if ( !empty( $notBot ) && \strpos( $notBot, 'z' ) ) {
			[ $ts, $hash ] = \explode( 'z', $notBot );
			$cookie[ 'ts' ] = (int)$ts;
			$cookie[ 'hash' ] = $hash;
		}

		return !empty( $cookie )
			   && ( $req->ts() < $cookie[ 'ts' ] )
			   && \hash_equals( $this->getHashForVisitorTS( $cookie[ 'ts' ] ), $cookie[ 'hash' ] );
	}

	public function getHashForVisitorTS( int $ts ) {
		return \hash_hmac( 'sha1', $ts.self::con()->this_req->ip, ( new InstallationID() )->id() );
	}
}