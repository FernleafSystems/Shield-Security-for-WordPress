<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\SureSend;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApi;
use FernleafSystems\Wordpress\Services\Services;

class SendSms extends BaseShieldNetApi {

	const API_ACTION = 'sure-send/sms';

	public function send2FA( \WP_User $to, string $code ) :bool {
		$meta = $this->getCon()->getUserMeta( $to );
		return $this->run(
			'2fa',
			$meta->sms_registration[ 'country' ],
			$meta->sms_registration[ 'phone' ],
			[
				'code'     => $code,
				'ip'       => Services::IP()->getRequestIp(),
				'username' => sanitize_key( $to->user_login ),
			]
		);
	}

	public function run( string $slug, string $countryTo, string $phoneTo, array $data ) :bool {
		$this->request_method = 'post';
		$this->params_body = [
			'slug'       => $slug,
			'country_to' => $countryTo,
			'phone_to'   => $phoneTo,
			'sms_data'   => array_merge(
				[
					'ts' => Services::Request()->ts(),
					'tz' => Services::WpGeneral()->getOption( 'timezone_string' ),
				],
				$data
			),
		];

		$raw = $this->sendReq();
		$success = is_array( $raw ) && empty( $raw[ 'error' ] );

		$this->getCon()->fireEvent(
			$success ? 'suresend_success' : 'suresend_fail',
			[
				'audit_params' => [
					'email' => $countryTo,
					'slug'  => $slug,
				]
			]
		);

		return $success;
	}

	protected function getApiRequestUrl() :string {
		return sprintf( '%s/%s', parent::getApiRequestUrl(), $this->params_body[ 'slug' ] );
	}
}