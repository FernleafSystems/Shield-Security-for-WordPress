<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

class LookupIpOnList {

	use Databases\Base\HandlerConsumer;
	use IPs\Components\IpAddressConsumer;

	/**
	 * @var string
	 */
	private $sListType;

	/**
	 * @var bool
	 */
	private $bIsBlocked;

	/**
	 * @param bool $bIncludeRanges
	 * @return Databases\IPs\EntryVO|null
	 * @version 8.6.0 - switched to lookup ranges first
	 */
	public function lookup( $bIncludeRanges = true ) {
		$IP = null;
		if ( !empty( $this->getIP() ) ) {
			if ( $bIncludeRanges ) {
				foreach ( $this->lookupRange() as $oMaybeIp ) {
					try {
						if ( Services::IP()->checkIp( $this->getIP(), $oMaybeIp->ip ) ) {
							$IP = $oMaybeIp;
							break;
						}
					}
					catch ( \Exception $e ) {
					}
				}
			}
			if ( !$IP instanceof Databases\IPs\EntryVO ) {
				$IP = $this->lookupIp();
			}
		}
		return $IP;
	}

	/**
	 * @return Databases\IPs\EntryVO|null
	 */
	public function lookupIp() {
		/** @var Databases\IPs\Select $oSelect */
		$oSelect = $this->getDbHandler()->getQuerySelector();

		if ( $this->getListType() == 'white' ) {
			$oSelect->filterByWhitelist();
		}
		elseif ( $this->getListType() == 'black' ) {
			$oSelect->filterByBlacklist();
			if ( !is_null( $this->isIpBlocked() ) ) {
				$oSelect->filterByBlocked( $this->isIpBlocked() );
			}
		}

		return $oSelect->filterByIsRange( false )
					   ->filterByIp( $this->getIP() )
					   ->first();
	}

	/**
	 * @return Databases\IPs\EntryVO[]
	 */
	public function lookupRange() {
		/** @var Databases\IPs\Select $oSelect */
		$oSelect = $this->getDbHandler()->getQuerySelector();

		if ( $this->getListType() == 'white' ) {
			$oSelect->filterByWhitelist();
		}
		elseif ( $this->getListType() == 'black' ) {
			$oSelect->filterByBlacklist();
			if ( !is_null( $this->isIpBlocked() ) ) {
				$oSelect->filterByBlocked( $this->isIpBlocked() );
			}
		}

		$aIps = $oSelect->filterByIsRange( true )->query();
		return is_array( $aIps ) ? $aIps : [];
	}

	/**
	 * @return string
	 */
	public function getListType() {
		return $this->sListType;
	}

	/**
	 * @return bool|null
	 */
	public function isIpBlocked() {
		return $this->bIsBlocked;
	}

	/**
	 * @param bool $bIsBlocked
	 * @return $this
	 */
	public function setIsIpBlocked( $bIsBlocked ) {
		$this->bIsBlocked = $bIsBlocked;
		return $this;
	}

	/**
	 * @return $this
	 */
	public function setListTypeBlack() {
		$this->sListType = 'black';
		return $this;
	}

	/**
	 * @return $this
	 */
	public function setListTypeWhite() {
		$this->sListType = 'white';
		return $this;
	}
}