<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\CaptureNotBot;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class NotBotHandler {

	use ExecOnce;
	use ModConsumer;

	public const LIFETIME = 600;
	public const SLUG = 'notbot';

	protected function canRun() :bool {
		return (bool)apply_filters( 'shield/can_run_antibot', true );
	}

	protected function run() {
		( new InsertNotBotJs() )->execute();
		if ( $this->opts()->isOpt( 'force_notbot', 'Y' ) ) {
			add_action( 'setup_theme', [ $this, 'sendNotBotNonceCookie' ] );
		}
	}

	/**
	 * Hooked to "setup_theme" as ActionData::Build() requires $wp_rewrite to be initialised,
	 * and this is the earliest we can hook into.
	 */
	public function sendNotBotNonceCookie() {
		Services::Response()->cookieSet(
			'shield-notbot-nonce',
			ActionData::Build( CaptureNotBot::class )[ ActionData::FIELD_NONCE ],
			60
		);
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
		return \hash_hmac( 'sha1', $ts.self::con()->this_req->ip, self::con()->getInstallationID()[ 'id' ] );
	}
}