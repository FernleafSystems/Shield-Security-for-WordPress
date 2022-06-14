<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\MachineRegisterFailedException;

class MachineRegister extends Base {

	const API_ACTION = 'watchers';

	/**
	 * @throws MachineRegisterFailedException
	 */
	public function run( string $machineID, string $password ) :bool {
		$this->request_method = 'post';
		$this->params_body = [
			'machine_id' => $machineID,
			'password'   => $password,
		];
		$raw = $this->sendReq();
		if ( !is_array( $raw ) || ( $raw[ 'message' ] ?? '' ) !== 'OK' ) {
			throw new MachineRegisterFailedException( sprintf( 'Failed to register machine: %s',
				var_export( $this->last_http_req->lastResponse->body, true ) ) );
		}
		return true;
	}
}