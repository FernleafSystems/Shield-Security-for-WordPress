<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	const LIST_MANUAL_WHITE = 'MW';
	const LIST_MANUAL_BLACK = 'MB';
	const LIST_AUTO_BLACK = 'AB';

	/**
	 * @var Lib\OffenseTracker
	 */
	private $oOffenseTracker;

	/**
	 * @var Lib\BlacklistHandler
	 */
	private $oBlacklistHandler;

	public function getBlacklistHandler() :Lib\BlacklistHandler {
		if ( !isset( $this->oBlacklistHandler ) ) {
			$this->oBlacklistHandler = ( new Lib\BlacklistHandler() )->setMod( $this );
		}
		return $this->oBlacklistHandler;
	}

	public function getDbHandler_IPs() :Shield\Databases\IPs\Handler {
		return $this->getDbH( 'ips' );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		$oIp = Services::IP();
		return $oIp->isValidIp_PublicRange( $oIp->getRequestIp() )
			   && ( $this->getDbHandler_IPs() instanceof Shield\Databases\IPs\Handler )
			   && $this->getDbHandler_IPs()->isReady()
			   && parent::isReadyToExecute();
	}

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( !defined( strtoupper( $opts->getOpt( 'auto_expire' ).'_IN_SECONDS' ) ) ) {
			$opts->resetOptToDefault( 'auto_expire' );
		}

		$nLimit = $opts->getOffenseLimit();
		if ( !is_int( $nLimit ) || $nLimit < 0 ) {
			$opts->resetOptToDefault( 'transgression_limit' );
		}

		$this->cleanPathWhitelist();
	}

	private function cleanPathWhitelist() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$opts->setOpt( 'request_whitelist', array_unique( array_filter( array_map(
			function ( $rule ) {
				$rule = strtolower( trim( $rule ) );
				if ( !empty( $rule ) ) {
					$toCheck = array_unique( [
						(string)parse_url( Services::WpGeneral()->getHomeUrl(), PHP_URL_PATH ),
						(string)parse_url( Services::WpGeneral()->getWpUrl(), PHP_URL_PATH ),
					] );
					$regEx = sprintf( '#^%s$#i', str_replace( 'STAR', '.*', preg_quote( str_replace( '*', 'STAR', $rule ), '#' ) ) );
					foreach ( $toCheck as $path ) {
						$slashPath = rtrim( $path, '/' ).'/';
						if ( preg_match( $regEx, $path ) || preg_match( $regEx, $slashPath ) ) {
							$rule = false;
							break;
						}
					}
				}
				return $rule;
			},
			$opts->getOpt( 'request_whitelist', [] ) // do not use Options getter as it formats into regex
		) ) ) );
	}

	public function loadOffenseTracker() :Lib\OffenseTracker {
		if ( !isset( $this->oOffenseTracker ) ) {
			$this->oOffenseTracker = new Lib\OffenseTracker( $this->getCon() );
		}
		return $this->oOffenseTracker;
	}

	public function getTextOptDefault( string $key ) :string {

		switch ( $key ) {

			case 'text_loginfailed':
				$text = sprintf( '%s: %s',
					__( 'Warning', 'wp-simple-firewall' ),
					__( 'Repeated login attempts that fail will result in a complete ban of your IP Address.', 'wp-simple-firewall' )
				);
				break;

			case 'text_remainingtrans':
				$text = sprintf( '%s: %s',
					__( 'Warning', 'wp-simple-firewall' ),
					__( 'You have %s remaining offenses(s) against this site and then your IP address will be completely blocked.', 'wp-simple-firewall' )
					.'<br/><strong>'.__( 'Seriously, stop repeating what you are doing or you will be locked out.', 'wp-simple-firewall' ).'</strong>'
				);
				break;

			default:
				$text = parent::getTextOptDefault( $key );
				break;
		}
		return $text;
	}
}