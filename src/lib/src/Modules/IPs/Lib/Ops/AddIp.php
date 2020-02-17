<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class AddIp
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops
 */
class AddIp {

	use Modules\ModConsumer;
	use Modules\IPs\Components\IpAddressConsumer;

	/**
	 * @return Databases\IPs\EntryVO|null
	 * @throws \Exception
	 */
	public function toAutoBlacklist() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		$oIpServ = Services::IP();

		$sIP = $this->getIP();
		if ( !$oIpServ->isValidIp( $sIP ) ) {
			throw new \Exception( 'IP address is not valid' );
		}

		$oIP = null;
		if ( !in_array( $sIP, Services::IP()->getServerPublicIPs() ) ) {
			$oIP = ( new LookupIpOnList() )
				->setDbHandler( $oMod->getDbHandler_IPs() )
				->setListTypeBlack()
				->setIP( $sIP )
				->lookup( false );
			if ( !$oIP instanceof Databases\IPs\EntryVO ) {
				$oIP = $this->add( $oMod::LIST_AUTO_BLACK, 'auto' );
			}

			$oMod->getDbHandler_IPs()
				 ->getQueryUpdater()
				 ->updateEntry( $oIP, [
					 'last_access_at' => Services::Request()->ts()
				 ] );
		}
		return $oIP;
	}

	/**
	 * @param string $sLabel
	 * @return Databases\IPs\EntryVO|null
	 * @throws \Exception
	 */
	public function toManualBlacklist( $sLabel = '' ) {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		$oIpServ = Services::IP();

		$sIP = $this->getIP();
		if ( !$oIpServ->isValidIp( $sIP ) && !$oIpServ->isValidIpRange( $sIP ) ) {
			throw new \Exception( 'IP address is not valid' );
		}

		$oIP = null;
		if ( !in_array( $sIP, $oIpServ->getServerPublicIPs() ) ) {

			if ( $oIpServ->isValidIpRange( $sIP ) ) {
				( new DeleteIp() )
					->setDbHandler( $oMod->getDbHandler_IPs() )
					->setIP( $sIP )
					->fromBlacklist();
			}

			$oIP = ( new LookupIpOnList() )
				->setDbHandler( $oMod->getDbHandler_IPs() )
				->setListTypeBlack()
				->setIP( $sIP )
				->lookup( false );

			if ( !$oIP instanceof Databases\IPs\EntryVO ) {
				$oIP = $this->add( $oMod::LIST_MANUAL_BLACK, $sLabel );
			}

			$aUpdateData = [
				'last_access_at' => Services::Request()->ts()
			];

			if ( $oIP->list != $oMod::LIST_MANUAL_BLACK ) {
				$aUpdateData[ 'list' ] = $oMod::LIST_MANUAL_BLACK;
			}
			if ( $oIP->label != $sLabel ) {
				$aUpdateData[ 'label' ] = $sLabel;
			}
			if ( $oIP->blocked_at == 0 ) {
				$aUpdateData[ 'blocked_at' ] = Services::Request()->ts();
			}

			$oMod->getDbHandler_IPs()
				 ->getQueryUpdater()
				 ->updateEntry( $oIP, $aUpdateData );
		}

		return $oIP;
	}

	/**
	 * @param string $sLabel
	 * @return Databases\IPs\EntryVO|null
	 * @throws \Exception
	 */
	public function toManualWhitelist( $sLabel = '' ) {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		$oIpServ = Services::IP();

		$sIP = $this->getIP();
		if ( !$oIpServ->isValidIp( $sIP ) && !$oIpServ->isValidIpRange( $sIP ) ) {
			throw new \Exception( 'IP address is not valid' );
		}

		if ( $oIpServ->isValidIpRange( $sIP ) ) {
			( new DeleteIp() )
				->setDbHandler( $oMod->getDbHandler_IPs() )
				->setIP( $sIP )
				->fromWhiteList();
		}

		$oIP = ( new LookupIpOnList() )
			->setDbHandler( $oMod->getDbHandler_IPs() )
			->setIP( $this->getIP() )
			->lookup( false );
		if ( !$oIP instanceof Databases\IPs\EntryVO ) {
			$oIP = $this->add( $oMod::LIST_MANUAL_WHITE, $sLabel );
		}

		$aUpdateData = [];
		if ( $oIP->list != $oMod::LIST_MANUAL_WHITE ) {
			$aUpdateData[ 'list' ] = $oMod::LIST_MANUAL_WHITE;
		}
		if ( !empty( $sLabel ) && $oIP->label != $sLabel ) {
			$aUpdateData[ 'label' ] = $sLabel;
		}
		if ( $oIP->blocked_at > 0 ) {
			$aUpdateData[ 'blocked_at' ] = 0;
		}
		if ( $oIP->transgressions > 0 ) {
			$aUpdateData[ 'transgressions' ] = 0;
		}

		if ( !empty( $aUpdateData ) ) {
			$oMod->getDbHandler_IPs()
				 ->getQueryUpdater()
				 ->updateEntry( $oIP, $aUpdateData );
		}

		return $oIP;
	}

	/**
	 * @param string $sList
	 * @param string $sLabel
	 * @return Databases\IPs\EntryVO|null
	 */
	private function add( $sList, $sLabel = '' ) {
		$oIP = null;

		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();

		// Never add a reserved IP to any black list
		$oDbh = $oMod->getDbHandler_IPs();

		/** @var Databases\IPs\EntryVO $oTempIp */
		$oTempIp = $oDbh->getVo();
		$oTempIp->ip = $this->getIP();
		$oTempIp->list = $sList;
		$oTempIp->label = empty( $sLabel ) ? __( 'No Label', 'wp-simple-firewall' ) : trim( $sLabel );

		if ( $oDbh->getQueryInserter()->insert( $oTempIp ) ) {
			/** @var Databases\IPs\EntryVO $oIP */
			$oIP = $oDbh->getQuerySelector()
						->setWheresFromVo( $oTempIp )
						->first();
		}

		return $oIP;
	}
}