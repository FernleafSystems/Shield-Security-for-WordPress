<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseSelect;

class Select extends BaseSelect {

	/**
	 * @return string[]
	 */
	public function getDistinctSeverity() {
		return $this->getDistinct_FilterAndSort( 'severity' );
	}

	/**
	 * @param string $sHash
	 * @return $this
	 */
	public function filterByHash( $sHash ) {
		if ( !empty( $sHash ) ) {
			$this->addWhereEquals( 'hash', $sHash );
		}
		return $this;
	}

	/**
	 * @return $this
	 */
	public function filterByIgnored() {
		return $this->addWhereNewerThan( 0, 'ignored_at' );
	}

	/**
	 * @return $this
	 */
	public function filterByNotIgnored() {
		return $this->addWhereEquals( 'ignored_at', 0 );
	}

	/**
	 * @param string $sScan
	 * @return $this
	 */
	public function filterByScan( $sScan ) {
		if ( !empty( $sScan ) ) {
			$this->addWhereEquals( 'scan', strtolower( $sScan ) );
		}
		return $this;
	}

	/**
	 * @param string $sScan
	 * @return int
	 */
	public function countForScan( $sScan ) {
		return $this->reset()
					->filterByNotIgnored()
					->filterByScan( $sScan )
					->count();
	}

	/**
	 * @param string $sScan
	 * @return EntryVO[]
	 */
	public function forScan( $sScan ) {
		return $this->reset()
					->filterByScan( $sScan )
					->query();
	}

	/**
	 * @return EntryVO
	 */
	public function getVo() {
		return Handler::getVo();
	}
}