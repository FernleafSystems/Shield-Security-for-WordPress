<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;

class RequestIsPublicWebOrigin extends Base {

	public const SLUG = 'request_is_public_web_origin';

	protected function getSubConditions() :array {
		return [
			'logic' => Constants::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => WpIsWpcli::class,
					'logic'      => Constants::LOGIC_INVERT,
				],
				[
					'conditions' => IsIpValidPublic::class,
				],
				[
					'conditions' => RequestIsServerLoopback::class,
					'logic'      => Constants::LOGIC_INVERT,
				],
			]
		];
	}
}