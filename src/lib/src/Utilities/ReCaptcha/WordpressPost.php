<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\ReCaptcha;

use ReCaptcha\RequestMethod;
use ReCaptcha\RequestParameters;

class WordpressPost implements RequestMethod {

	/**
	 * URL to which requests are sent via wp_remote_post.
	 * @const string
	 */
	public const SITE_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

	/**
	 * Submit the wp_remote_post request with the specified parameters.
	 * @param RequestParameters $params Request parameters
	 * @return string Body of the reCAPTCHA response
	 */
	public function submit( RequestParameters $params ) {
		$aResponse = wp_remote_post(
			self::SITE_VERIFY_URL,
			[
				'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
				'body'    => $params->toArray()
			]
		);

		$sResponseBody = '';
		if ( !is_wp_error( $aResponse ) && \is_array( $aResponse ) && isset( $aResponse[ 'body' ] ) ) {
			$sResponseBody = $aResponse[ 'body' ];
		}
		return $sResponseBody;
	}
}
