<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\MachinePasswordResetFailedException;

class MachinePasswordReset extends Base {

	const API_ACTION = 'watchers/reset';

	/**
	 * @throws MachinePasswordResetFailedException
	 */
	public function run( string $machineID, string $password ) :bool {
		$this->request_method = 'post';
		$this->params_body = [
			'machine_id' => $machineID,
			'password'   => $password,
		];

		$raw = $this->sendReq();
		if ( !is_array( $raw ) || (int)$this->last_http_req->lastResponse->getCode() !== 200 ) {
			throw new MachinePasswordResetFailedException( sprintf( 'machine password reset failed: %s',
				var_export( $this->last_http_req->lastResponse->body, true ) ) );
		}
		return true;
	}
}