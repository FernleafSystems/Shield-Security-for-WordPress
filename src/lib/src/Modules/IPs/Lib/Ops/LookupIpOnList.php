<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 16.0
 */
class LookupIpOnList {

	use Databases\Base\HandlerConsumer;
	use IPs\Components\IpAddressConsumer;

	/**
	 * @var string
	 */
	private $listType;

	/**
	 * @var bool
	 */
	private $isBlocked;

	/**
	 * @return Databases\IPs\EntryVO|null
	 * @version 8.6.0 - switched to lookup ranges first
	 */
	public function lookup( bool $includeRanges = true ) {
		$IP = null;
		if ( !empty( $this->getIP() ) ) {
			if ( $includeRanges ) {
				foreach ( $this->lookupRange() as $maybe ) {
					try {
						if ( Services::IP()->checkIp( $this->getIP(), $maybe->ip ) ) {
							$IP = $maybe;
							break;
						}
					}
					catch ( \Exception $e ) {
					}
				}
			}
			if ( empty( $IP ) ) {
				$IP = $this->lookupIp();
			}
		}
		return $IP;
	}

	/**
	 * @return Databases\IPs\EntryVO|null
	 */
	public function lookupIp() {
		/** @var Databases\IPs\Select $select */
		$select = $this->getDbHandler()->getQuerySelector();

		if ( $this->getListType() == 'white' ) {
			$select->filterByWhitelist();
		}
		elseif ( $this->getListType() == 'black' ) {
			$select->filterByBlacklist();
			if ( !is_null( $this->isIpBlocked() ) ) {
				$select->filterByBlocked( $this->isIpBlocked() );
			}
		}

		return $select->filterByIsRange( false )
					  ->filterByIp( $this->getIP() )
					  ->first();
	}

	/**
	 * @return Databases\IPs\EntryVO[]
	 */
	public function lookupRange() {
		/** @var Databases\IPs\Select $select */
		$select = $this->getDbHandler()->getQuerySelector();

		if ( $this->getListType() == 'white' ) {
			$select->filterByWhitelist();
		}
		elseif ( $this->getListType() == 'black' ) {
			$select->filterByBlacklist();
			if ( !is_null( $this->isIpBlocked() ) ) {
				$select->filterByBlocked( $this->isIpBlocked() );
			}
		}

		$IPs = $select->filterByIsRange( true )->query();
		return is_array( $IPs ) ? $IPs : [];
	}

	/**
	 * @return string
	 */
	public function getListType() {
		return $this->listType;
	}

	/**
	 * @return bool|null
	 */
	public function isIpBlocked() {
		return $this->isBlocked;
	}

	/**
	 * @return $this
	 */
	public function setIsIpBlocked( bool $blocked ) {
		$this->isBlocked = $blocked;
		return $this;
	}

	/**
	 * @return $this
	 */
	public function setListTypeBlock() {
		$this->listType = 'black';
		return $this;
	}

	/**
	 * @return $this
	 */
	public function setListTypeCrowdsec() {
		$this->listType = 'crowdsec';
		return $this;
	}

	/**
	 * @return $this
	 */
	public function setListTypeBypass() {
		$this->listType = 'white';
		return $this;
	}
}