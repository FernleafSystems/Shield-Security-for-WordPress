<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\HttpRequest;

/**
 * @property int         $api_version
 * @property string      $lookup_url_stub
 * @property string      $request_method
 * @property array       $headers
 * @property int         $timeout
 * @property HttpRequest $last_http_req
 * @property array       $params_body
 * @property array       $params_query
 */
abstract class BaseApi extends DynPropertiesClass {

	const DEFAULT_URL_STUB = '';
	const API_ACTION = '';
	const DEFAULT_API_VERSION = '1';

	/**
	 * @return array|null
	 */
	protected function sendReq() {
		$httpReq = Services::HttpRequest();

		$reqParams = $this->getRequestParams();

		switch ( $this->request_method ) {

			case 'post':
				$reqParams[ 'body' ] = $this->params_body;
				$reqSuccess = $httpReq->post( $this->getApiRequestUrl(), $reqParams );
				break;

			case 'get':
			default:
				// Doing it in the ['body'] on some sites fails with the params not passed through to query string.
				// if they're not using the newer WP Request() class. WP 4.6+
				$reqSuccess = $httpReq->get(
					add_query_arg( $this->params_query, $this->getApiRequestUrl() ),
					$reqParams
				);
				break;
		}

		if ( $reqSuccess ) {
			$response = empty( $httpReq->lastResponse->body ) ? [] : @json_decode( $httpReq->lastResponse->body, true );
		}
		else {
			$response = null;
		}

		$this->last_http_req = $httpReq;
		return $response;
	}

	protected function getApiRequestUrl() :string {
		return sprintf( '%s/v%s/%s', $this->lookup_url_stub, $this->api_version, static::API_ACTION );
	}

	protected function getRequestParams() :array {
		return [
			'timeout' => $this->timeout,
			'headers' => $this->headers,
		];
	}

	/**
	 * @return string[]
	 */
	protected function getRequestParamKeys() :array {
		return [];
	}

	/**
	 * @return mixed
	 */
	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {

			case 'headers':
			case 'params_query':
				if ( !is_array( $value ) ) {
					$value = [];
				}
				break;

			case 'params_body':
				if ( !is_array( $value ) ) {
					$value = [];
				}
				if ( $this->headers[ 'Content-Type' ] ?? '' === 'application/json' ) {
					$value = json_encode( $value );
				}
				break;

			case 'request_method':
				$value = empty( $value ) ? 'get' : strtolower( $value );
				break;

			case 'api_version':
				if ( empty( $value ) ) {
					$value = static::DEFAULT_API_VERSION;
				}
				break;

			case 'lookup_url_stub':
				if ( empty( $value ) ) {
					$value = static::DEFAULT_URL_STUB;
				}
				$value = rtrim( $value, '/' );
				break;

			case 'timeout':
				if ( empty( $value ) || !is_numeric( $value ) ) {
					$value = 60;
				}
				break;

			default:
				break;
		}

		return $value;
	}
}