<?php

use FernleafSystems\Wordpress\Plugin\Shield;

class ICWP_WPSF_Processor_Statistics extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @return \ICWP_WPSF_Processor_Statistics_Tally|mixed
	 */
	protected function getTallyProcessor() {
		return $this->getSubPro( 'tally' );
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'tally' => 'ICWP_WPSF_Processor_Statistics_Tally',
		];
	}
}