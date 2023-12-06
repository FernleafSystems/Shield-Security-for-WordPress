<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;

class RequestIsPublicWebOrigin extends Base {

	public const SLUG = 'request_is_public_web_origin';

	public function getDescription() :string {
		return __( "Does the request originate from the public web.", 'wp-simple-firewall' );
	}

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