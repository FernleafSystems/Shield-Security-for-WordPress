<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\SureSend;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApi;
use FernleafSystems\Wordpress\Services\Services;

class SendEmail extends BaseShieldNetApi {

	use ModConsumer;

	const API_ACTION = 'sure-send/email';

	public function send2FA( string $to, string $code ) :bool {
		return $this->run(
			'2fa',
			$to,
			[
				'code' => $code,
				'ip'   => Services::IP()->getRequestIp(),
			]
		);
	}

	public function run( string $slug, string $to, array $data ) :bool {
		$this->request_method = 'post';
		$this->params_body = [
			'slug'       => $slug,
			'email_to'   => $to,
			'email_data' => $data,
		];

		$raw = $this->sendReq();
		return is_array( $raw ) && !empty( $raw[ 'success' ] );
	}

	protected function getApiRequestUrl() :string {
		return sprintf( '%s/%s', parent::getApiRequestUrl(), $this->params_body[ 'slug' ] );
	}
}