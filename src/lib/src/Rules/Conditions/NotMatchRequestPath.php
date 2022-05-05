<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class NotMatchRequestPath extends MatchRequestPath {

	use Traits\RequestPath;

	const SLUG = 'not_match_request_path';

	protected function execConditionCheck() :bool {
		return !parent::execConditionCheck();
	}
}