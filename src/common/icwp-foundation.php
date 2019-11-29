<?php

/**
 * Class ICWP_WPSF_Foundation
 * @deprecated 8.4
 */
class ICWP_WPSF_Foundation {

	const DEFAULT_SERVICE_PREFIX = 'icwp_wpsf_';

	/**
	 * @param string $sSuffix
	 * @return string
	 */
	protected function prefix( $sSuffix ) {
		return self::DEFAULT_SERVICE_PREFIX.$sSuffix;
	}
}