<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports;

trait Common {

	/**
	 * @param string $sFrequency
	 * @return $this
	 */
	public function filterByFrequency( $sFrequency ) {
		return $this->addWhere( 'frequency', $sFrequency );
	}

	/**
	 * @param string $sType
	 * @return $this
	 */
	public function filterByType( $sType ) {
		if ( in_array( $sType, [ Handler::TYPE_INFO, Handler::TYPE_ALERT ] ) ) {
			$this->addWhere( 'type', $sType );
		}
		return $this;
	}
}