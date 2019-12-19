<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\HandlerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;
use FernleafSystems\Wordpress\Services\Services;

class LookupIpOnList {

	use HandlerConsumer;

	/**
	 * @var string
	 */
	private $sIp;

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
	 * @return IPs\EntryVO|null
	 */
	public function lookup( $bIncludeRanges = true ) {
		$oIp = $this->lookupIp();
		if ( $bIncludeRanges && !$oIp instanceof IPs\EntryVO ) {
			foreach ( $this->lookupRange() as $oMaybeIp ) {
				try {
					if ( Services::IP()->checkIp( $this->getIp(), $oMaybeIp->ip ) ) {
						$oIp = $oMaybeIp;
						break;
					}
				}
				catch ( \Exception $oE ) {
				}
			}
		}
		return $oIp;
	}

	/**
	 * @return IPs\EntryVO|null
	 */
	public function lookupIp() {
		/** @var IPs\Select $oSelect */
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
					   ->filterByIp( $this->getIp() )
					   ->first();
	}

	/**
	 * @return IPs\EntryVO[]
	 */
	public function lookupRange() {
		/** @var IPs\Select $oSelect */
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
	public function getIp() {
		return $this->sIp;
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
	 * @param string $sIp
	 * @return $this
	 */
	public function setIp( $sIp ) {
		$this->sIp = $sIp;
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

	/**
	 * @return string
	 * @deprecated 8.5
	 */
	public function getList() {
		return '';
	}

	/**
	 * @param string $sList
	 * @return $this
	 * @deprecated 8.5
	 */
	public function setList( $sList ) {
		return $this;
	}
}