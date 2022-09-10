<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\DownloadDecisionsStreamFailedException;

class DecisionsDownload extends BaseAuth {

	const API_ACTION = 'decisions/stream';

	/**
	 * @throws DownloadDecisionsStreamFailedException
	 */
	public function run() :array {
		$this->request_method = 'get';
		$decisions = $this->sendReq();
		if ( !is_array( $decisions ) || !isset( $decisions[ 'new' ] ) || !isset( $decisions[ 'deleted' ] ) ) {
			error_log( var_export( $this->getApiRequestUrl(), true ) );
			error_log( var_export( $this->getRequestParams(), true ) );
			error_log( var_export( $this->params_body, true ) );
			error_log( var_export( $this->params_query, true ) );
			error_log( var_export( $this->headers, true ) );
			throw new DownloadDecisionsStreamFailedException( sprintf( 'Failed to download decisions: %s',
				var_export( $this->last_http_req->lastResponse->body, true ) ) );
		}
		return $decisions;
	}
}