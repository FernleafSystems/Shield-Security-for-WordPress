<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\MachineAlreadyRegisteredException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\MachineRegisterFailedException;

class MachineRegister extends Base {

	public const API_ACTION = 'watchers';

	/**
	 * @throws MachineAlreadyRegisteredException
	 * @throws MachineRegisterFailedException
	 */
	public function run( string $machineID, string $password ) :bool {
		$this->request_method = 'post';
		$this->params_body = [
			'machine_id' => $machineID,
			'password'   => $password,
		];

		$raw = $this->sendReq();
		$lastResponse = $this->last_http_req->lastResponse;

		if ( !\is_array( $raw ) || empty( $raw[ 'message' ] ) ) {
			throw new MachineRegisterFailedException( sprintf( 'Failed to register machine: %s', var_export( $lastResponse->body, true ) ) );
		}

		if ( $lastResponse->getCode() === 500 && $raw[ 'message' ] === 'User already registered.' ) {
			throw new MachineAlreadyRegisteredException( sprintf( 'Machine already registered: %s',
				var_export( $this->last_http_req->lastResponse->body, true ) ) );
		}

		if ( $lastResponse->getCode() !== 200 || $raw[ 'message' ] !== 'OK' ) {
			throw new MachineRegisterFailedException( sprintf( 'Machine register failed: %s',
				var_export( $this->last_http_req->lastResponse->body, true ) ) );
		}

		return true;
	}
}