<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\DownloadDecisionsStreamFailedException;

class DecisionsDownload extends BaseAuth {

	public const API_ACTION = 'decisions/stream';

	/**
	 * @throws DownloadDecisionsStreamFailedException
	 */
	public function run() :array {
		$this->request_method = 'get';
		$decisions = $this->sendReq();
		if ( !\is_array( $decisions ) || !isset( $decisions[ 'new' ] ) || !isset( $decisions[ 'deleted' ] ) ) {
			throw new DownloadDecisionsStreamFailedException( sprintf( 'Failed to download decisions: %s',
				var_export( $this->last_http_req->lastResponse->body, true ) ) );
		}
		return $decisions;
	}
}