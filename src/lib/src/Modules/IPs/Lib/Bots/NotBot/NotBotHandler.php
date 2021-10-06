<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class NotBotHandler extends ExecOnceModConsumer {

	const LIFETIME = 60;
	const SLUG = 'notbot';

	private $useCookies;

	public function __construct( bool $useCookies = false ) {
		$this->useCookies = $useCookies;
	}

	protected function canRun() :bool {
		return (bool)apply_filters( 'shield/can_run_antibot', true );
	}

	protected function run() {
		( new InsertNotBotJs() )
			->setMod( $this->getMod() )
			->execute();
		$this->registerFrontPageLoad();
		$this->registerLoginPageLoad();
	}

	private function registerFrontPageLoad() {
		add_action( $this->getCon()->prefix( 'pre_plugin_shutdown' ), function () {
			$req = Services::Request();
			if ( $req->isGet() && ( is_page() || is_single() || is_front_page() || is_home() ) ) {
				/** @var ModCon $mod */
				$mod = $this->getMod();
				$mod->getBotSignalsController()
					->getEventListener()
					->fireEventForIP( Services::IP()->getRequestIp(), 'frontpage_load' );
			}
		} );
	}

	private function registerLoginPageLoad() {
		add_action( 'login_footer', function () {
			$req = Services::Request();
			if ( $req->isGet() ) {
				/** @var ModCon $mod */
				$mod = $this->getMod();
				$mod->getBotSignalsController()
					->getEventListener()
					->fireEventForIP( Services::IP()->getRequestIp(), 'loginpage_load' );
			}
		} );
	}

	public function registerAsNotBot() :bool {
		if ( $this->useCookies ) {
			$ts = Services::Request()->ts() +
				  apply_filters( 'shield/notbot_cookie_life', self::LIFETIME );
			Services::Response()->cookieSet(
				$this->getMod()->prefix( self::SLUG ),
				sprintf( '%sz%s', $ts, $this->getHashForVisitorTS( $ts ) ),
				self::LIFETIME
			);
		}
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
			$timestamp.Services::IP()->getRequestIp(),
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