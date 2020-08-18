<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\AlwaysDeliver;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApi;
use FernleafSystems\Wordpress\Services\Services;

class SendEmail extends BaseShieldNetApi {

	use ModConsumer;

	const API_ACTION = 'always-deliver/email';

	/**
	 * @param string $to
	 * @param string $code
	 * @return bool
	 */
	public function send2FA( $to, $code ) {
		return $this->run(
			'2fa',
			$to,
			[
				'code' => $code,
				'ip'   => Services::IP()->getRequestIp(),
			]
		);
	}

	/**
	 * @param string $slug
	 * @param string $to
	 * @param array  $data
	 * @return bool
	 */
	public function run( $slug, $to, array $data ) {
		$this->request_method = 'post';
		$this->params_body = [
			'slug'       => $slug,
			'email_to'   => $to,
			'email_data' => $data,
		];

		$raw = $this->sendReq();
		return is_array( $raw ) && !empty( $raw[ 'success' ] );
	}

	/**
	 * @return string
	 */
	protected function getApiRequestUrl() {
		return sprintf( '%s/%s', parent::getApiRequestUrl(), $this->params_body[ 'slug' ] );
	}
}