<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class NotBotHandler extends ExecOnceModConsumer {

	const LIFETIME = 600;
	const SLUG = 'notbot';

	protected function canRun() :bool {
		return (bool)apply_filters( 'shield/can_run_antibot', true );
	}

	protected function run() {
		( new InsertNotBotJs() )
			->setMod( $this->getMod() )
			->run();
		$this->registerFrontPageLoad();
		$this->maybeDeleteCookie();
	}

	private function registerFrontPageLoad() {
		add_action( 'wp', function () {
			$req = Services::Request();
			if ( $req->isGet() && is_front_page() ) {
				/** @var ModCon $mod */
				$mod = $this->getMod();
				$mod->getBotSignalsController()
					->getEventListener()
					->fireEventForIP( Services::IP()->getRequestIp(), 'frontpage_load' );
			}
		} );
	}

	private function maybeDeleteCookie() {
		$cookie = $this->getCookieParts();
		if ( !empty( $cookie ) && $cookie[ 'ts' ] - Services::Request()->ts() < 300 ) {
			$this->clearCookie();
		}
	}

	public function registerAsNotBot() :bool {
		$ts = Services::Request()->ts() + self::LIFETIME;
		Services::Response()->cookieSet(
			$this->getMod()->prefix( self::SLUG ),
			sprintf( '%sz%s', $ts, $this->getHashForVisitorTS( $ts ) ),
			self::LIFETIME
		);
		$this->getCon()->fireEvent( 'bottrack_notbot' );
		return true;
	}

	public function clearCookie() :bool {
		Services::Response()->cookieSet(
			$this->getMod()->prefix( self::SLUG ),
			'',
			-self::LIFETIME
		);
		return true;
	}

	public function hasCookie() :bool {
		$cookie = $this->getCookieParts();
		return !empty( $cookie )
			   && ( Services::Request()->ts() < $cookie[ 'ts' ] )
			   && hash_equals( $this->getHashForVisitorTS( (int)$cookie[ 'ts' ] ), $cookie[ 'hash' ] );
	}

	protected function getHashForVisitorTS( int $timestamp ) {
		return hash_hmac( 'sha1',
			$timestamp.(string)Services::IP()->getRequestIp(),
			$this->getCon()->getSiteInstallationId()
		);
	}

	private function getCookieParts() :array {
		$parts = [];
		$req = Services::Request();
		$notBot = $req->cookie( $this->getMod()->prefix( self::SLUG ), '' );
		if ( !empty( $notBot ) && strpos( $notBot, 'z' ) ) {
			list( $ts, $hash ) = explode( 'z', $notBot );
			$parts[ 'ts' ] = $ts;
			$parts[ 'hash' ] = $hash;
		}
		return $parts;
	}
}