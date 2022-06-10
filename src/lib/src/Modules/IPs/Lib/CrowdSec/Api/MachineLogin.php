<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\FailedToMachineLoginException;

class MachineLogin extends Base {

	const API_ACTION = 'watchers/login';

	/**
	 * @throws FailedToMachineLoginException
	 */
	public function run( string $machineID, string $password, array $scenarios = [] ) :array {
		$this->request_method = 'post';
		$this->params_body = [
			'password'   => $password,
			'machine_id' => $machineID,
			'scenarios'  => $scenarios
		];

		$raw = $this->sendReq();
		if ( !is_array( $raw ) || $raw[ 'code' ] !== 200 ) {
			throw new FailedToMachineLoginException( sprintf( 'login failed: %s',
				var_export( $this->last_http_req->lastResponse->body, true ) ) );
		}
		return $raw;
	}
}