<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Services\Services;

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
			if ( !empty( $IP ) ) {
				$this->getCon()->fireEvent( 'ip_block_auto', [ 'audit_params' => [ 'ip' => $this->getIP() ] ] );
			}
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
	 * @return Databases\IPs\EntryVO|null
	 * @throws \Exception
	 */
	public function toManualBlacklist( string $label = '' ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$srvIP = Services::IP();

		$ip = $this->getIP();
		if ( !$srvIP->isValidIp( $ip ) && !$srvIP->isValidIpRange( $ip ) ) {
			throw new \Exception( "IP address isn't valid." );
		}

		if ( !$this->getCon()->isPremiumActive() ) {
			throw new \Exception( __( 'Sorry, this is a PRO-only feature.', 'wp-simple-firewall' ) );
		}

		$IP = null;
		if ( !in_array( $ip, $srvIP->getServerPublicIPs() ) ) {

			if ( $srvIP->isValidIpRange( $ip ) ) {
				( new DeleteIp() )
					->setMod( $mod )
					->setIP( $ip )
					->fromBlacklist();
			}

			$IP = ( new LookupIpOnList() )
				->setDbHandler( $mod->getDbHandler_IPs() )
				->setListTypeBlock()
				->setIP( $ip )
				->lookup( false );

			if ( empty( $IP ) ) {
				$IP = $this->add( $mod::LIST_MANUAL_BLACK, $label );
				if ( !empty( $IP ) ) {
					$this->getCon()->fireEvent( 'ip_block_manual', [ 'audit_params' => [ 'ip' => $this->getIP() ] ] );
				}
			}

			$updateData = [
				'last_access_at' => Services::Request()->ts()
			];

			if ( $IP->list != $mod::LIST_MANUAL_BLACK ) {
				$updateData[ 'list' ] = $mod::LIST_MANUAL_BLACK;
			}
			if ( $IP->label != $label ) {
				$updateData[ 'label' ] = $label;
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
	 * @return Databases\IPs\EntryVO|null
	 * @throws \Exception
	 */
	public function toManualWhitelist( string $label = '' ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$srvIP = Services::IP();

		$ip = $this->getIP();
		if ( !$srvIP->isValidIp( $ip ) && !$srvIP->isValidIpRange( $ip ) ) {
			throw new \Exception( "IP address isn't valid." );
		}

		if ( $srvIP->isValidIpRange( $ip ) ) {
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
			$IP = $this->add( $mod::LIST_MANUAL_WHITE, $label );
			if ( !empty( $IP ) ) {
				$this->getCon()->fireEvent( 'ip_bypass_add', [ 'audit_params' => [ 'ip' => $this->getIP() ] ] );
			}
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
	 * @return Databases\IPs\EntryVO|null
	 * @throws \Exception
	 */
	private function add( string $list, string $label = '', int $accessAt = 0 ) {
		$IP = null;

		/** @var ModCon $mod */
		$mod = $this->getMod();

		// Never add a reserved IP to any black list
		$dbh = $mod->getDbHandler_IPs();

		/** @var Databases\IPs\EntryVO $tmp */
		$tmp = $dbh->getVo();
		$tmp->ip = $this->getIP();
		$tmp->list = $list;
		$tmp->label = (string)$label;
		$tmp->last_access_at = $accessAt;

		if ( $dbh->getQueryInserter()->insert( $tmp ) ) {
			/** @var Databases\IPs\EntryVO $IP */
			$IP = $dbh->getQuerySelector()
					  ->byId( Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' ) );
		}

		if ( !$IP instanceof Databases\IPs\EntryVO ) {
			throw new \Exception( "IP couldn't be added to the database." );
		}

		return $IP;
	}
}