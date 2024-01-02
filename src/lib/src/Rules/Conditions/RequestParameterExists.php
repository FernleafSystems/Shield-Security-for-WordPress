<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Enum,
	Utility
};
use FernleafSystems\Wordpress\Services\Services;

class RequestParameterExists extends Base {

	use Traits\TypeRequest;

	public function getDescription() :string {
		return __( 'Does the request contain a parameter with the provide name.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		$req = Services::Request();
		$this->addConditionTriggerMeta( 'match_pattern', $this->p->match_pattern );

		$paramSources = [];
		if ( $this->p->req_param_source === 'headers' ) {
			$paramSources[] = $req->headers();
		}
		else {
			if ( \str_contains( $this->p->req_param_source, 'get' ) ) {
				$paramSources[] = $req->query;
			}
			if ( \str_contains( $this->p->req_param_source, 'post' ) ) {
				$paramSources[] = $req->post;
			}
			if ( \str_contains( $this->p->req_param_source, 'cookie' ) ) {
				$paramSources[] = $req->cookie_copy;
			}
		}

		$matches = false;
		foreach ( \array_map( '\array_keys', $paramSources ) as $paramsSource ) {
			foreach ( $paramsSource as $paramName ) {
				if ( ( new Utility\PerformConditionMatch( $paramName, $this->p->match_pattern, $this->p->match_type ) )->doMatch() ) {
					$matches = true;
					break;
				}
			}
		}

		return $matches;
	}

	public function getParamsDef() :array {
		$sources = [
			'get'             => '$_GET',
			'post'            => '$_POST',
			'cookie'          => '$_COOKIE',
			'get_post'        => '$_GET & $_POST',
			'get_post_cookie' => '$_GET & $_POST & $_COOKIE',
			'headers'         => 'HTTP Request Headers',
		];
		return [
			'req_param_source' => [
				'type'        => Enum\EnumParameters::TYPE_ENUM,
				'type_enum'   => \array_keys( $sources ),
				'enum_labels' => $sources,
				'default'     => 'get_post',
				'label'       => __( 'Which Parameters To Check', 'wp-simple-firewall' ),
			],
			'match_type'       => [
				'type'      => Enum\EnumParameters::TYPE_ENUM,
				'type_enum' => Enum\EnumMatchTypes::MatchTypesForStrings(),
				'default'   => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS_I,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_pattern'    => [
				'type'  => Enum\EnumParameters::TYPE_STRING,
				'label' => __( 'Compare Parameter Name To', 'wp-simple-firewall' ),
			],
		];
	}
}