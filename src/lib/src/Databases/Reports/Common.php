<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports;

trait Common {

	/**
	 * @param string $sInterval
	 * @return $this
	 */
	public function filterByInterval( $sInterval ) {
		return $this->addWhere( 'interval', $sInterval );
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