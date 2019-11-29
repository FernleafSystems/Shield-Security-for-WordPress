<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;
use FernleafSystems\Wordpress\Services\Services;

class LookupIpOnList {

	use ModConsumer;

	/**
	 * @var string
	 */
	private $sIp;

	/**
	 * @var string
	 */
	private $sList;

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
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		/** @var IPs\Select $oSelect */
		$oSelect = $oMod->getDbHandler_IPs()->getQuerySelector();

		return $oSelect->filterByIsRange( false )
					   ->filterByIp( $this->getIp() )
					   ->filterByList( $this->getList() )
					   ->first();
	}

	/**
	 * @return IPs\EntryVO[]
	 */
	public function lookupRange() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		/** @var IPs\Select $oSelect */
		$oSelect = $oMod->getDbHandler_IPs()->getQuerySelector();

		$aIps = $oSelect->filterByIsRange( true )
						->filterByList( $this->getList() )
						->query();
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
	public function getList() {
		return $this->sList;
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
	 * @param string $sList
	 * @return $this
	 */
	public function setList( $sList ) {
		$this->sList = $sList;
		return $this;
	}
}