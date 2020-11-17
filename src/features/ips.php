<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 10.1
 */
class ICWP_WPSF_FeatureHandler_Ips extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	const LIST_MANUAL_WHITE = 'MW';
	const LIST_MANUAL_BLACK = 'MB';
	const LIST_AUTO_BLACK = 'AB';

	/**
	 * @var IPs\Lib\OffenseTracker
	 */
	private $oOffenseTracker;

	/**
	 * @var IPs\Lib\BlacklistHandler
	 */
	private $oBlacklistHandler;

	/**
	 * @return IPs\Lib\BlacklistHandler
	 */
	public function getBlacklistHandler() {
		if ( !isset( $this->oBlacklistHandler ) ) {
			$this->oBlacklistHandler = ( new IPs\Lib\BlacklistHandler() )->setMod( $this );
		}
		return $this->oBlacklistHandler;
	}

	/**
	 * @return IPs\Lib\BlacklistHandler
	 */
	public function getProcessor() {
		return $this->getBlacklistHandler();
	}

	/**
	 * @return false|Shield\Databases\IPs\Handler
	 */
	public function getDbHandler_IPs() {
		return $this->getDbH( 'ips' );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToExecute() {
		$oIp = Services::IP();
		return $oIp->isValidIp_PublicRange( $oIp->getRequestIp() )
			   && ( $this->getDbHandler_IPs() instanceof Shield\Databases\IPs\Handler )
			   && $this->getDbHandler_IPs()->isReady()
			   && parent::isReadyToExecute();
	}

	protected function preProcessOptions() {
		/** @var IPs\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( !defined( strtoupper( $oOpts->getOpt( 'auto_expire' ).'_IN_SECONDS' ) ) ) {
			$oOpts->resetOptToDefault( 'auto_expire' );
		}

		$nLimit = $oOpts->getOffenseLimit();
		if ( !is_int( $nLimit ) || $nLimit < 0 ) {
			$oOpts->resetOptToDefault( 'transgression_limit' );
		}

		$this->cleanPathWhitelist();
	}

	private function cleanPathWhitelist() {
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		$opts->setOpt( 'request_whitelist', array_unique( array_filter( array_map(
			function ( $sRule ) {
				$sRule = strtolower( trim( $sRule ) );
				if ( !empty( $sRule ) ) {
					$aToCheck = [
						parse_url( Services::WpGeneral()->getHomeUrl(), PHP_URL_PATH ),
						parse_url( Services::WpGeneral()->getWpUrl(), PHP_URL_PATH ),
					];
					$sRegEx = sprintf( '#^%s$#i', str_replace( 'STAR', '.*', preg_quote( str_replace( '*', 'STAR', $sRule ), '#' ) ) );
					foreach ( $aToCheck as $sPath ) {
						$sSlashPath = rtrim( $sPath, '/' ).'/';
						if ( preg_match( $sRegEx, $sPath ) || preg_match( $sRegEx, $sSlashPath ) ) {
							$sRule = false;
							break;
						}
					}
				}
				return $sRule;
			},
			$opts->getOpt( 'request_whitelist', [] ) // do not use Options getter as it formats into regex
		) ) ) );
	}

	/**
	 * @return IPs\Lib\OffenseTracker
	 */
	public function loadOffenseTracker() {
		if ( !isset( $this->oOffenseTracker ) ) {
			$this->oOffenseTracker = new IPs\Lib\OffenseTracker( $this->getCon() );
		}
		return $this->oOffenseTracker;
	}

	/**
	 * @param string $sOptKey
	 * @return string
	 */
	public function getTextOptDefault( $sOptKey ) {

		switch ( $sOptKey ) {

			case 'text_loginfailed':
				$sText = sprintf( '%s: %s',
					__( 'Warning', 'wp-simple-firewall' ),
					__( 'Repeated login attempts that fail will result in a complete ban of your IP Address.', 'wp-simple-firewall' )
				);
				break;

			case 'text_remainingtrans':
				$sText = sprintf( '%s: %s',
					__( 'Warning', 'wp-simple-firewall' ),
					__( 'You have %s remaining offenses(s) against this site and then your IP address will be completely blocked.', 'wp-simple-firewall' )
					.'<br/><strong>'.__( 'Seriously, stop repeating what you are doing or you will be locked out.', 'wp-simple-firewall' ).'</strong>'
				);
				break;

			default:
				$sText = parent::getTextOptDefault( $sOptKey );
				break;
		}
		return $sText;
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() :string {
		return 'IPs';
	}

	/**
	 * @deprecated 10.1
	 */
	protected function addFilterIpsToWhiteList() {
	}
}