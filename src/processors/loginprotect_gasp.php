<?php

/**
 * Class ICWP_WPSF_Processor_LoginProtect_Gasp
 * @deprecated 9.0
 */
class ICWP_WPSF_Processor_LoginProtect_Gasp extends ICWP_WPSF_Processor_LoginProtect_Base {

	/**
	 * @return string
	 */
	protected function buildFormItems() {
		return $this->getGaspLoginHtml();
	}

	/**
	 * @return string
	 */
	private function getGaspLoginHtml() {
		return '';
	}

	/**
	 * @throws \Exception
	 */
	protected function performCheckWithException() {
		return;
	}
}