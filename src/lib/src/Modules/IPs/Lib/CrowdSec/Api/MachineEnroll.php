<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\MachineEnrollFailedException;

class MachineEnroll extends BaseAuth {

	public const API_ACTION = 'watchers/enroll';

	/**
	 * @throws MachineEnrollFailedException
	 */
	public function run( string $enrollID, string $name, array $tags = [] ) :bool {
		$this->request_method = 'post';
		$this->params_body = [
			'attachment_key' => $enrollID,
			'name'           => $name,
			'overwrite'      => true,
			'tags'           => \array_filter(
				$tags,
				function ( $tag ) {
					return !empty( $tag ) && \is_string( $tag );
				}
			)
		];
		$raw = $this->sendReq();
		if ( !\is_array( $raw ) || ( $raw[ 'message' ] ?? '' ) !== 'OK' ) {
			throw new MachineEnrollFailedException( sprintf( 'Failed to enroll machine: %s',
				var_export( $this->last_http_req->lastResponse->body, true ) ) );
		}
		return true;
	}
}