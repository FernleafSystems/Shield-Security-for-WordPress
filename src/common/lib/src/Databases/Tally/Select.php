<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Tally;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

	/**
	 * @param string $sKey
	 * @return $this
	 */
	public function filterByParentStatKey( $sKey ) {
		return $this->addWhereEquals( 'parent_stat_key', $sKey );
	}

	/**
	 * @param string $sKey
	 * @return $this
	 */
	public function filterByStatKey( $sKey ) {
		return $this->addWhereEquals( 'stat_key', $sKey );
	}

	/**
	 * @param string $sStatKey
	 * @param string $sParentStatKey
	 * @return EntryVO|\stdClass|null
	 */
	public function retrieveStat( $sStatKey, $sParentStatKey = '' ) {
		if ( !empty( $sParentStatKey ) ) {
			$this->filterByParentStatKey( $sParentStatKey );
		}
		$oR = $this->filterByStatKey( $sStatKey )
				   ->setOrderBy( 'created_at', 'DESC' )
				   ->first();
		return $oR;
	}
}