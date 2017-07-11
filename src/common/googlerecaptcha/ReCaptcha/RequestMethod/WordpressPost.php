<?php
/**
 * This is a PHP library that handles calling reCAPTCHA.
 * @copyright Copyright (c) 2015, Google Inc.
 * @link      http://www.google.com/recaptcha
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace ReCaptcha\RequestMethod;

use ReCaptcha\RequestMethod;
use ReCaptcha\RequestParameters;

/**
 * Class WordpressPost
 * @package ReCaptcha\RequestMethod
 */
class WordpressPost implements RequestMethod {

	/**
	 * URL to which requests are sent via wp_remote_post.
	 * @const string
	 */
	const SITE_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

	/**
	 * Submit the wp_remote_post request with the specified parameters.
	 * @param RequestParameters $params Request parameters
	 * @return string Body of the reCAPTCHA response
	 */
	public function submit( RequestParameters $params ) {
		$aResponse = wp_remote_post(
			self::SITE_VERIFY_URL,
			array(
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => $params->toArray()
			)
		);

		$sResponseBody = '';
		if ( !is_wp_error( $aResponse ) && is_array( $aResponse ) && isset( $aResponse[ 'body' ] ) ) {
			$sResponseBody = $aResponse[ 'body' ];
		}
		return $sResponseBody;
	}
}
