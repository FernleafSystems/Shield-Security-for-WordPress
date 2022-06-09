<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\FailedToDownloadDecisionsStreamException;

class DecisionsDownload extends BaseAuth {

	const API_ACTION = 'decisions/stream';

	/**
	 * @throws FailedToDownloadDecisionsStreamException
	 */
	public function run() :array {
		$this->request_method = 'get';
		$raw = $this->sendReq();
		if ( !is_array( $raw ) ) {
			throw new FailedToDownloadDecisionsStreamException( sprintf( 'Failed to download decisions: %s',
				var_export( $this->last_http_req->lastResponse->body, true ) ) );
		}
		return $raw;
	}
}