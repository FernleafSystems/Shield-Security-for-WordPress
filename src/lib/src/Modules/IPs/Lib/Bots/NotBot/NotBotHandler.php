<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\CaptureNotBot;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class NotBotHandler extends ExecOnceModConsumer {

	const LIFETIME = 300;
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
		$this->sendNotBotNonceCookie();
		$this->registerFrontPageLoad();
		$this->registerLoginPageLoad();
	}

	protected function sendNotBotNonceCookie() {
		Services::Response()->cookieSet(
			'shield-notbot-nonce',
			ActionData::Build( CaptureNotBot::SLUG )[ ActionData::FIELD_NONCE ],
			15
		);
	}

	private function registerFrontPageLoad() {
		add_action( $this->getCon()->prefix( 'pre_plugin_shutdown' ), function () {
			if ( Services::Request()->isGet() && did_action( 'wp' )
				 && ( is_page() || is_single() || is_front_page() || is_home() ) ) {
				/** @var ModCon $mod */
				$mod = $this->getMod();
				$mod->getBotSignalsController()
					->getEventListener()
					->fireEventForIP( $this->getCon()->this_req->ip, 'frontpage_load' );
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
					->fireEventForIP( $this->getCon()->this_req->ip, 'loginpage_load' );
			}
		} );
	}

	public function registerAsNotBot() :bool {
		if ( $this->useCookies ) {
			$cookieLife = apply_filters( 'shield/notbot_cookie_life', self::LIFETIME );
			$ts = Services::Request()->ts() + $cookieLife;
			Services::Response()->cookieSet(
				$this->getCon()->prefix( self::SLUG ),
				sprintf( '%sz%s', $ts, $this->getHashForVisitorTS( $ts ) ),
				$cookieLife
			);
		}
		$this->getCon()->fireEvent( 'bottrack_notbot' );
		return true;
	}

	public function clearCookie() :bool {
		Services::Response()->cookieSet(
			$this->getCon()->prefix( self::SLUG ),
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

	protected function getHashForVisitorTS( int $ts ) {
		return hash_hmac( 'sha1', $ts.$this->getCon()->this_req->ip, $this->getCon()->getInstallationID()[ 'id' ] );
	}

	private function getCookieParts() :array {
		$parts = [];
		$req = Services::Request();
		$notBot = $req->cookie( $this->getCon()->prefix( self::SLUG ), '' );
		if ( !empty( $notBot ) && strpos( $notBot, 'z' ) ) {
			list( $ts, $hash ) = explode( 'z', $notBot );
			$parts[ 'ts' ] = $ts;
			$parts[ 'hash' ] = $hash;
		}
		return $parts;
	}
}