<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Headers;

/**
 * @deprecated 19.1
 */
class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Processor {

	/**
	 * @var bool
	 */
	private $pushed = false;

	/**
	 * @var array
	 */
	private $headers;

	protected function run() {
	}

	protected function getPushHeadersEarly() :bool {
		return false;
	}

	/**
	 * Tries to ensure duplicate headers are not sent. Previously sent/supplied headers take priority.
	 * @param array $wpHeaders
	 */
	public function addToHeaders( $wpHeaders ) {
		return $wpHeaders;
	}

	public function sendHeaders() {
	}

	private function gatherSecurityHeaders() :array {
		return \array_filter( $this->getHeaders() );
	}

	/**
	 * @return string[] - array of all previously sent headers. Keys are header names, values are header values.
	 */
	private function getAlreadySentHeaders() :array {
		return [];
	}

	private function getXFrameHeader() :array {
		return [];
	}

	private function getXssProtectionHeader() :array {
		return  [];
	}

	private function getContentTypeOptionHeader() :array {
		return  [];
	}

	private function getReferrerPolicyHeader() :array {
		return  [];
	}

	private function setContentSecurityPolicyHeader() :array {
		return  [];
	}

	private function getHeaders() :array {
		if ( !isset( $this->headers ) || !\is_array( $this->headers ) ) {
			$this->headers = [];
		}
		return \array_unique( $this->headers );
	}

	private function addHeader( array $header ) {
	}
}