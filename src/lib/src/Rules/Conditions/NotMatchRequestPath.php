<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class NotMatchRequestPath extends MatchRequestPath {

	use Traits\RequestPath;

	public const SLUG = 'not_match_request_path';

	public function getName() :string {
		return __( 'Does the request useragent NOT match the given useragents.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return !parent::execConditionCheck();
	}
}