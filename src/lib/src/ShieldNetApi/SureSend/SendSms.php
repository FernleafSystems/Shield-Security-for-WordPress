<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\SureSend;

use FernleafSystems\Wordpress\Services\Services;

class SendSms extends \FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApi {

	public const API_ACTION = 'sure-send/sms';

	/**
	 * @throws \Exception
	 */
	public function send2FA( \WP_User $to, string $code ) :bool {
		$meta = self::con()->user_metas->for( $to );
		return $this->run(
			'2fa',
			$meta->sms_registration[ 'country' ],
			$meta->sms_registration[ 'phone' ],
			[
				'code'     => $code,
				'ip'       => self::con()->this_req->ip,
				'username' => sanitize_key( $to->user_login ),
			]
		);
	}

	/**
	 * @throws \Exception
	 */
	public function run( string $slug, string $countryTo, string $phoneTo, array $data ) :bool {
		$this->request_method = 'post';
		$this->params_body = [
			'slug'       => $slug,
			'country_to' => $countryTo,
			'phone_to'   => $phoneTo,
			'sms_data'   => \array_merge(
				[
					'ts' => Services::Request()->ts(),
					'tz' => Services::WpGeneral()->getOption( 'timezone_string' ),
				],
				$data
			),
		];

		$raw = $this->sendReq();
		$success = \is_array( $raw ) && empty( $raw[ 'error' ] );

		self::con()->fireEvent(
			$success ? 'suresend_success' : 'suresend_fail',
			[
				'audit_params' => [
					'email' => $countryTo,
					'slug'  => $slug,
				]
			]
		);

		if ( !$success ) {
			throw new \Exception( $raw[ 'message' ] ?? 'Unknown Error' );
		}

		return true;
	}

	protected function getApiRequestUrl() :string {
		return sprintf( '%s/%s', parent::getApiRequestUrl(), $this->params_body[ 'slug' ] );
	}
}