<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\MachineLoginFailedException;

class MachineLogin extends Base {

	public const API_ACTION = 'watchers/login';

	/**
	 * @throws MachineLoginFailedException
	 */
	public function run( string $machineID, string $password, array $scenarios = [] ) :array {
		$this->request_method = 'post';
		$this->params_body = [
			'machine_id' => $machineID,
			'password'   => $password,
			'scenarios'  => $scenarios
		];

		$raw = $this->sendReq();
		if ( !\is_array( $raw ) || ( $raw[ 'code' ] ?? 0 ) !== 200 ) {
			throw new MachineLoginFailedException( sprintf( 'login failed: %s',
				var_export( $this->last_http_req->lastResponse->body, true ) ) );
		}
		return $raw;
	}
}