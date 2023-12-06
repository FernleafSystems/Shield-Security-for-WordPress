<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class IsRequestStatus404 extends Base {

	public const SLUG = 'is_request_status_404';

	public function getDescription() :string {
		return __( 'Is the request an HTTP 404.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'conditions' => MatchRequestStatusCode::class,
			'params'     => [
				'code' => '404',
			],
		];
	}
}