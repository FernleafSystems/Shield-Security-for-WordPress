<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class IsRequestStatus404 extends Base {

	public const SLUG = 'is_request_status_404';

	protected function getSubConditions() :array {
		return [
			'conditions' => MatchRequestStatusCode::class,
			'params'     => [
				'code' => '404',
			],
		];
	}
}