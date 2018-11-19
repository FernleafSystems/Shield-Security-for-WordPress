<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseSelect;

class Select extends BaseSelect {

	/**
	 * @param string $sIp
	 * @return $this
	 */
	public function filterByIp( $sIp ) {
		return $this->addWhereEquals( 'ip', $sIp );
	}

	/**
	 * @param string $nLastAccessAfter
	 * @return $this
	 */
	public function filterByLastAccessAfter( $nLastAccessAfter ) {
		return $this->addWhereNewerThan( $nLastAccessAfter, 'last_access_at' );
	}

	/**
	 * @param string $sList
	 * @return $this
	 */
	public function filterByList( $sList ) {
		if ( !empty( $sList ) ) {
			$this->addWhereEquals( 'list', $sList );
		}
		return $this;
	}

	/**
	 * @param string $sList
	 * @return EntryVO[]
	 */
	public function allFromList( $sList ) {
		/** @var EntryVO[] $aRes */
		$aRes = $this->reset()
					 ->filterByList( $sList )
					 ->query();
		return $aRes;
	}

	/**
	 * @return EntryVO
	 */
	public function getVo() {
		return Handler::getVo();
	}
}