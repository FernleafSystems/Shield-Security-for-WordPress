<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Options extends Base\ShieldOptions {

	/**
	 * @return string[]
	 * @deprecated 9.2.0
	 */
	public function getDbColumns_Reports() {
		return $this->getDef( 'reports_table_columns' );
	}

	/**
	 * @return string
	 */
	public function getDbTable_Reports() {
		return $this->getCon()->prefixOption( $this->getDef( 'reports_table_name' ) );
	}

	/**
	 * @return string
	 */
	public function getFrequencyAlert() {
		return $this->getFrequency( 'alert' );
	}

	/**
	 * @return string
	 */
	public function getFrequencyInfo() {
		return $this->getFrequency( 'info' );
	}

	/**
	 * @param string $sType
	 * @return string
	 */
	private function getFrequency( $sType ) {
		$sKey = 'frequency_'.$sType;
		$sDefault = $this->getOptDefault( $sKey );
		return ( $this->isPremium() || in_array( $this->getOpt( $sKey ), [ 'disabled', $sDefault ] ) )
			? $this->getOpt( $sKey )
			: $sDefault;
	}
}