<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Route\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Exceptions\OptionDoesNotExistException;

abstract class BaseSingle extends Base {

	public function getRoutePath() :string {
		return '/(?P<key>[0-9a-z_]{3,})';
	}

	public function customValidateRequestArg( $value, \WP_REST_Request $request, string $reqArgKey ) {

		switch ( $reqArgKey ) {

			case 'key':
				if ( !$this->optKeyExists( $value ) ) {
					throw new OptionDoesNotExistException( sprintf( "Option with key %s doesn't exist", $value ) );
				}
				break;

			default:
				return parent::customValidateRequestArg( $value, $request, $reqArgKey );
		}

		return true;
	}

	protected function getRouteArgsDefaults() :array {
		return [
			'key' => [
				'description' => 'The option key',
				'type'        => 'string',
				'required'    => true,
			],
		];
	}
}