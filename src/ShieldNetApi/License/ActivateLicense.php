<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\License;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\Exceptions\FailedLicenseRequestHttpException;

/**
 * TODO: Confirm endpoint with ShieldSecurity.com
 */
class ActivateLicense extends \FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApiV2 {

	public const API_ACTION = 'licenses/activate';

	/**
	 * @throws FailedLicenseRequestHttpException
	 */
	public function activate( string $apiKey, string $activationUrl ) :array {
		$this->shield_net_params_required = true;
		$this->request_method = 'post';
		$this->params_body = [
			'api_key' => $apiKey,
			'url'     => $activationUrl,
		];

		$result = $this->sendReq();

		if ( !\is_array( $result ) ) {
			throw new FailedLicenseRequestHttpException( 'HTTP Request Failed' );
		}

		return $result;
	}
}
