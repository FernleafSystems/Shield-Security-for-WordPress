<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\PerformConditionMatch;

class MatchRequestCountryCode extends Base {

	use Traits\TypeRequest;

	protected function execConditionCheck() :bool {
		$meta = $this->req->ip_meta_record;
		return !empty( $meta ) && ( new PerformConditionMatch(
				(string)$meta->country_iso2, $this->p->match_code, EnumMatchTypes::MATCH_TYPE_EQUALS
			) )->doMatch();
	}

	public function getDescription() :string {
		return __( "Does the method of the request match the given specified method to match.", 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'match_code' => [
				'type'         => EnumParameters::TYPE_STRING,
				'label'        => sprintf( '%s (ISO 3166-1 alpha-2)', __( 'Country Code', 'wp-simple-firewall' ) ),
				'verify_regex' => '/^([A-Z]{2})$/'
			],
		];
	}
}