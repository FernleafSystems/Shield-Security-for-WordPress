<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Translations;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApiV2;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class DownloadTranslation extends BaseShieldNetApiV2 {

	public const API_ACTION = 'translations/download';

	private string $locale = '';

	/**
	 * Download a translation file for the given locale.
	 *
	 * @return string|null Binary .mo file content, or null on error
	 */
	public function download( string $locale ) :?string {
		if ( !$this->isValidLocaleFormat( $locale ) ) {
			return null;
		}

		$this->locale = $locale;
		$this->request_method = 'get';

		$content = $this->sendReqBinary();

		// Validate it's not an error response (JSON) and not empty
		if ( empty( $content ) || $this->looksLikeJsonError( $content ) ) {
			return null;
		}

		return $content;
	}

	/**
	 * Send request and return raw binary content instead of JSON-decoded array.
	 */
	protected function sendReqBinary() :?string {
		$httpReq = Services::HttpRequest();

		$reqSuccess = $httpReq->get(
			URL::Build( $this->getApiRequestUrl(), $this->params_query ),
			$this->getRequestParams()
		);

		if ( $reqSuccess && !empty( $httpReq->lastResponse->body ) ) {
			return $httpReq->lastResponse->body;
		}

		$this->last_http_req = $httpReq;
		return null;
	}

	public function __get( string $key ) {
		$value = parent::__get( $key );

		if ( $key === 'params_query' ) {
			$value[ 'locale' ] = $this->locale;
		}

		return $value;
	}

	private function isValidLocaleFormat( string $locale ) :bool {
		// Matches: de_DE, fr_FR, en_US, ja, zh_CN, etc.
		return (bool)\preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?$/', $locale );
	}

	/**
	 * Check if the response looks like a JSON error rather than binary content.
	 */
	private function looksLikeJsonError( string $content ) :bool {
		// .mo files start with magic bytes, not '{' or '['
		$firstChar = $content[0] ?? '';
		if ( $firstChar === '{' || $firstChar === '[' ) {
			$decoded = @\json_decode( $content, true );
			return \is_array( $decoded ) && isset( $decoded[ 'error_code' ] );
		}
		return false;
	}
}
