<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;

class RequestHasPostParameters extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'request_has_post_parameters';

	protected function execConditionCheck() :bool {
		$post = $this->req->request->post;
		return \is_array( $post ) && !empty( $post );
	}

	public function getDescription() :string {
		return __( "Does the request have any POST parameters.", 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => MatchRequestMethod::class,
					'params'     => [
						'match_method' => 'POST',
					],
				],
				[
					'conditions' => $this->getDefaultConditionCheckCallable(),
				],
			]
		];
	}
}