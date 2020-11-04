<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
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
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$oReq = Services::Request();

		$sIP = $this->getIP();
		if ( !Services::IP()->isValidIp( $sIP ) ) {
			throw new \Exception( "IP address isn't valid." );
		}
		if ( in_array( $sIP, Services::IP()->getServerPublicIPs() ) ) {
			throw new \Exception( 'Will not black mark our own server IP' );
		}

		$oIP = ( new LookupIpOnList() )
			->setDbHandler( $mod->getDbHandler_IPs() )
			->setListTypeBlack()
			->setIP( $sIP )
			->lookup( false );
		if ( !$oIP instanceof Databases\IPs\EntryVO ) {
			$oIP = $this->add( $mod::LIST_AUTO_BLACK, 'auto', $oReq->ts() );
		}

		// Edge-case: the IP is on the list but the last access long-enough passed
		// that it's set to be removed by the cron - the IP is basically expired.
		// We just reset the transgressions
		/** @var Modules\IPs\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oIP->transgressions > 0
			 && ( $oReq->ts() - $oOpts->getAutoExpireTime() > (int)$oIP->last_access_at ) ) {
			$mod->getDbHandler_IPs()
				 ->getQueryUpdater()
				 ->updateEntry( $oIP, [
					 'last_access_at' => Services::Request()->ts(),
					 'transgressions' => 0
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
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$oIpServ = Services::IP();

		$sIP = $this->getIP();
		if ( !$oIpServ->isValidIp( $sIP ) && !$oIpServ->isValidIpRange( $sIP ) ) {
			throw new \Exception( "IP address isn't valid." );
		}

		$oIP = null;
		if ( !in_array( $sIP, $oIpServ->getServerPublicIPs() ) ) {

			if ( $oIpServ->isValidIpRange( $sIP ) ) {
				( new DeleteIp() )
					->setDbHandler( $mod->getDbHandler_IPs() )
					->setIP( $sIP )
					->fromBlacklist();
			}

			$oIP = ( new LookupIpOnList() )
				->setDbHandler( $mod->getDbHandler_IPs() )
				->setListTypeBlack()
				->setIP( $sIP )
				->lookup( false );

			if ( !$oIP instanceof Databases\IPs\EntryVO ) {
				$oIP = $this->add( $mod::LIST_MANUAL_BLACK, $sLabel );
			}

			$aUpdateData = [
				'last_access_at' => Services::Request()->ts()
			];

			if ( $oIP->list != $mod::LIST_MANUAL_BLACK ) {
				$aUpdateData[ 'list' ] = $mod::LIST_MANUAL_BLACK;
			}
			if ( $oIP->label != $sLabel ) {
				$aUpdateData[ 'label' ] = $sLabel;
			}
			if ( $oIP->blocked_at == 0 ) {
				$aUpdateData[ 'blocked_at' ] = Services::Request()->ts();
			}

			$mod->getDbHandler_IPs()
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
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$oIpServ = Services::IP();

		$sIP = $this->getIP();
		if ( !$oIpServ->isValidIp( $sIP ) && !$oIpServ->isValidIpRange( $sIP ) ) {
			throw new \Exception( "IP address isn't valid." );
		}

		if ( $oIpServ->isValidIpRange( $sIP ) ) {
			( new DeleteIp() )
				->setDbHandler( $mod->getDbHandler_IPs() )
				->setIP( $sIP )
				->fromWhiteList();
		}

		$oIP = ( new LookupIpOnList() )
			->setDbHandler( $mod->getDbHandler_IPs() )
			->setIP( $this->getIP() )
			->lookup( false );
		if ( !$oIP instanceof Databases\IPs\EntryVO ) {
			$oIP = $this->add( $mod::LIST_MANUAL_WHITE, $sLabel );
		}

		$aUpdateData = [];
		if ( $oIP->list != $mod::LIST_MANUAL_WHITE ) {
			$aUpdateData[ 'list' ] = $mod::LIST_MANUAL_WHITE;
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
			$mod->getDbHandler_IPs()
				 ->getQueryUpdater()
				 ->updateEntry( $oIP, $aUpdateData );
		}

		return $oIP;
	}

	/**
	 * @param string   $sList
	 * @param string   $sLabel
	 * @param int|null $nLastAccessAt
	 * @return Databases\IPs\EntryVO|null
	 * @throws \Exception
	 */
	private function add( $sList, $sLabel = '', $nLastAccessAt = null ) {
		$oIP = null;

		/** @var ModCon $mod */
		$mod = $this->getMod();

		// Never add a reserved IP to any black list
		$oDbh = $mod->getDbHandler_IPs();

		/** @var Databases\IPs\EntryVO $oTempIp */
		$oTempIp = $oDbh->getVo();
		$oTempIp->ip = $this->getIP();
		$oTempIp->list = $sList;
		$oTempIp->label = empty( $sLabel ) ? __( 'No Label', 'wp-simple-firewall' ) : trim( $sLabel );
		if ( is_numeric( $nLastAccessAt ) && $nLastAccessAt > 0 ) {
			$oTempIp->last_access_at = $nLastAccessAt;
		}

		if ( $oDbh->getQueryInserter()->insert( $oTempIp ) ) {
			/** @var Databases\IPs\EntryVO $oIP */
			$oIP = $oDbh->getQuerySelector()
						->setWheresFromVo( $oTempIp )
						->first();
		}

		if ( !$oIP instanceof Databases\IPs\EntryVO ) {
			throw new \Exception( "IP couldn't be added to the database." );
		}

		return $oIP;
	}
}