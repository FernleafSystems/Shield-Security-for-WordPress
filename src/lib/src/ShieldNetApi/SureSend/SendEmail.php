<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\SureSend;

use FernleafSystems\Wordpress\Services\Services;

class SendEmail extends \FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApi {

	public const API_ACTION = 'sure-send/email';

	public function send2FA( \WP_User $to, string $code ) :bool {
		return $this->run(
			'2fa',
			$to->user_email,
			[
				'code' => $code,
				'ip'   => self::con()->this_req->ip,
			]
		);
	}

	public function run( string $slug, string $to, array $data ) :bool {
		$this->request_method = 'post';
		$this->params_body = [
			'slug'       => $slug,
			'email_to'   => $to,
			'email_data' => \array_merge(
				[
					'ts' => Services::Request()->ts(),
					'tz' => Services::WpGeneral()->getOption( 'timezone_string' ),
				],
				$data
			),
		];

		$raw = $this->sendReq();
		$success = \is_array( $raw ) && empty( $raw[ 'error' ] );

		self::con()->comps->events->fireEvent(
			$success ? 'suresend_success' : 'suresend_fail',
			[
				'audit_params' => [
					'email' => $to,
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