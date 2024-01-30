<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

/**
 * @deprecated 18.5.8
 */
class NotMatchRequestPath extends MatchRequestPath {

	public const SLUG = 'not_match_request_path';

	public function getDescription() :string {
		return __( 'Does the request useragent NOT match the given useragents.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return !parent::execConditionCheck();
	}
}