<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\MatchUseragentsUnavailableException;

/**
 * @property string $match_useragent
 */
class MatchRequestUseragent extends Base {

	use Traits\TypeRequest;
	use Traits\UserAgent;

	public const SLUG = 'match_request_useragent';

	public function getDescription() :string {
		return __( 'Does the request useragent match the given useragent.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		$agent = $this->match_useragent;
		try {
			if ( empty( $agent ) ) {
				throw new MatchUseragentsUnavailableException();
			}
			$this->addConditionTriggerMeta( 'matched_useragent', $agent );
			$matched = \stripos( $this->getUserAgent(), $agent ) !== false;
		}
		catch ( \Exception $e ) {
			$matched = false;
		}
		return $matched;
	}

	public function getParamsDef() :array {
		return [
			'match_useragent' => [
				'type'  => 'string',
				'label' => __( 'Match Useragent', 'wp-simple-firewall' ),
			],
		];
	}
}