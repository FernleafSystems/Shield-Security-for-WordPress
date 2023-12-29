<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class MatchRequestMethod extends Base {

	use Traits\TypeRequest;

	public function getDescription() :string {
		return __( "Does the method of the request match the given specified method to match.", 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		$methods = [
			'GET',
			'POST',
			'HEAD',
			'OPTIONS',
			'PUT',
			'PATCH',
			'DELETE',
		];
		return [
			'match_method'  => [
				'type'        => EnumParameters::TYPE_ENUM,
				'type_enum'   => $methods,
				'enum_labels' => \array_combine( $methods, $methods ),
				'default'     => 'GET',
				'label'       => __( 'Redirect Method', 'wp-simple-firewall' ),
			],
		];
	}
}