<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Bots;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class AntiBotHandler {

	const LIFETIME = 600;
	const SLUG = 'bitbot';
	use ModConsumer;
	use ExecOnce;

	private $hashTested = false;

	protected function canRun() :bool {
		return (bool)apply_filters( 'shield/can_run_antibot',
			!Services::WpUsers()->isUserLoggedIn() );
	}

	protected function run() {
		( new InsertJs() )
			->setMod( $this->getMod() )
			->run();
		$this->maybeDeleteCookie();
	}

	private function maybeDeleteCookie() {
		$cookie = $this->getCookieParts();
		if ( !empty( $cookie ) && $cookie[ 'ts' ] - Services::Request()->ts() < 60 ) {
			$this->clearCookie();
		}
	}

	public function setCookie() :bool {
		$ts = Services::Request()->ts() + self::LIFETIME;
		Services::Response()->cookieSet(
			$this->getMod()->prefix( self::SLUG ),
			sprintf( '%sz%s', $ts, $this->getHashForVisitorTS( $ts ) ),
			self::LIFETIME
		);
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

	public function verify() :bool {
		$valid = false;
		$req = Services::Request();
		$cookie = $this->getCookieParts();
		if ( !$this->hashTested && !empty( $cookie ) ) {
			$valid = ( $req->ts() < $cookie[ 'ts' ] )
					 && !array_key_exists( $cookie[ 'hash' ], $this->getBotHashes() )
					 && hash_equals( $this->getHashForVisitorTS( (int)$cookie[ 'ts' ] ), $cookie[ 'hash' ] );
			if ( $valid ) {
				$this->addBotHash( $cookie[ 'hash' ] );
			}
			$this->clearCookie();
			$this->hashTested = true; // we only test hashes once per request
		}
		$this->getCon()->fireEvent( 'antibot_'.( $valid ? 'pass' : 'fail' ) );
		return $valid;
	}

	protected function getHashForVisitorTS( int $timestamp ) {
		return hash_hmac( 'sha1',
			$timestamp.(string)Services::IP()->getRequestIp(),
			$this->getCon()->getSiteInstallationId()
		);
	}

	protected function getBotHashes() :array {
		$hashes = $this->getOptions()->getOpt( 'used_bot_hashes' );
		return is_array( $hashes ) ? $hashes : [];
	}

	protected function addBotHash( $hash ) {
		$hashes = $this->getBotHashes();
		$hashes[ $hash ] = Services::Request()->ts();
		$this->getOptions()->setOpt( 'used_bot_hashes', array_filter(
			$hashes,
			function ( $ts ) {
				return Services::Request()->ts() - self::LIFETIME < $ts;
			}
		) );
	}

	private function getCookieParts() :array {
		$parts = [];
		$req = Services::Request();
		$bitBot = $req->cookie( $this->getMod()->prefix( self::SLUG ), '' );
		if ( !empty( $bitBot ) && strpos( $bitBot, 'z' ) ) {
			list( $ts, $hash ) = explode( 'z', $bitBot );
			$parts[ 'ts' ] = $ts;
			$parts[ 'hash' ] = $hash;
		}
		return $parts;
	}
}