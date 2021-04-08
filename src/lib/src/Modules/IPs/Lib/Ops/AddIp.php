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
		$req = Services::Request();

		$ip = $this->getIP();
		if ( !Services::IP()->isValidIp( $ip ) ) {
			throw new \Exception( "IP address isn't valid." );
		}
		if ( in_array( $ip, Services::IP()->getServerPublicIPs() ) ) {
			throw new \Exception( 'Will not black mark our own server IP' );
		}

		$IP = ( new LookupIpOnList() )
			->setDbHandler( $mod->getDbHandler_IPs() )
			->setListTypeBlock()
			->setIP( $ip )
			->lookup( false );
		if ( !$IP instanceof Databases\IPs\EntryVO ) {
			$IP = $this->add( $mod::LIST_AUTO_BLACK, 'auto', $req->ts() );
		}

		// Edge-case: the IP is on the list but the last access long-enough passed
		// that it's set to be removed by the cron - the IP is basically expired.
		// We just reset the transgressions
		/** @var Modules\IPs\Options $opts */
		$opts = $this->getOptions();
		if ( $IP->transgressions > 0 && ( $req->ts() - $opts->getAutoExpireTime() > (int)$IP->last_access_at ) ) {
			$mod->getDbHandler_IPs()
				->getQueryUpdater()
				->updateEntry( $IP, [
					'last_access_at' => Services::Request()->ts(),
					'transgressions' => 0
				] );
		}
		return $IP;
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

		$IP = null;
		if ( !in_array( $sIP, $oIpServ->getServerPublicIPs() ) ) {

			if ( $oIpServ->isValidIpRange( $sIP ) ) {
				( new DeleteIp() )
					->setMod( $mod )
					->setIP( $sIP )
					->fromBlacklist();
			}

			$IP = ( new LookupIpOnList() )
				->setDbHandler( $mod->getDbHandler_IPs() )
				->setListTypeBlock()
				->setIP( $sIP )
				->lookup( false );

			if ( !$IP instanceof Databases\IPs\EntryVO ) {
				$IP = $this->add( $mod::LIST_MANUAL_BLACK, $sLabel );
			}

			$updateData = [
				'last_access_at' => Services::Request()->ts()
			];

			if ( $IP->list != $mod::LIST_MANUAL_BLACK ) {
				$updateData[ 'list' ] = $mod::LIST_MANUAL_BLACK;
			}
			if ( $IP->label != $sLabel ) {
				$updateData[ 'label' ] = $sLabel;
			}
			if ( $IP->blocked_at == 0 ) {
				$updateData[ 'blocked_at' ] = Services::Request()->ts();
			}

			$mod->getDbHandler_IPs()
				->getQueryUpdater()
				->updateEntry( $IP, $updateData );
		}

		return $IP;
	}

	/**
	 * @param string $label
	 * @return Databases\IPs\EntryVO|null
	 * @throws \Exception
	 */
	public function toManualWhitelist( $label = '' ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$oIpServ = Services::IP();

		$ip = $this->getIP();
		if ( !$oIpServ->isValidIp( $ip ) && !$oIpServ->isValidIpRange( $ip ) ) {
			throw new \Exception( "IP address isn't valid." );
		}

		if ( $oIpServ->isValidIpRange( $ip ) ) {
			( new DeleteIp() )
				->setMod( $mod )
				->setIP( $ip )
				->fromWhiteList();
		}

		$IP = ( new LookupIpOnList() )
			->setDbHandler( $mod->getDbHandler_IPs() )
			->setIP( $this->getIP() )
			->lookup( false );
		if ( !$IP instanceof Databases\IPs\EntryVO ) {
			$this->getCon()->fireEvent( 'ip_bypass' );
			$IP = $this->add( $mod::LIST_MANUAL_WHITE, $label );
		}

		$updateData = [];
		if ( $IP->list != $mod::LIST_MANUAL_WHITE ) {
			$updateData[ 'list' ] = $mod::LIST_MANUAL_WHITE;
		}
		if ( !empty( $label ) && $IP->label != $label ) {
			$updateData[ 'label' ] = $label;
		}
		if ( $IP->blocked_at > 0 ) {
			$updateData[ 'blocked_at' ] = 0;
		}
		if ( $IP->transgressions > 0 ) {
			$updateData[ 'transgressions' ] = 0;
		}

		if ( !empty( $updateData ) ) {
			$mod->getDbHandler_IPs()
				->getQueryUpdater()
				->updateEntry( $IP, $updateData );
		}

		return $IP;
	}

	/**
	 * @param string   $list
	 * @param string   $sLabel
	 * @param int|null $nLastAccessAt
	 * @return Databases\IPs\EntryVO|null
	 * @throws \Exception
	 */
	private function add( string $list, $sLabel = '', $nLastAccessAt = null ) {
		$oIP = null;

		/** @var ModCon $mod */
		$mod = $this->getMod();

		// Never add a reserved IP to any black list
		$oDbh = $mod->getDbHandler_IPs();

		/** @var Databases\IPs\EntryVO $oTempIp */
		$oTempIp = $oDbh->getVo();
		$oTempIp->ip = $this->getIP();
		$oTempIp->list = $list;
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