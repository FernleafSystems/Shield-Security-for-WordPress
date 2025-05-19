<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route;

class Signal extends BaseWorpdrive {

	public function getRoutePath() :string {
		return sprintf( '/signal/(?P<context>%s)', \implode( '|', $this->enumContexts() ) );
	}

	/**
	 * @inheritDoc
	 */
	protected function customValidateRequestArg( $value, \WP_REST_Request $request, string $reqArgKey ) {
		switch ( $reqArgKey ) {
			case 'signal_data':
				$valid = true;
				if ( !\is_array( $value ) ) {
					$valid = new \WP_Error( 'Not an array.' );
				}
				elseif ( $request->get_param( 'context' ) === 'backup' ) {
					if ( empty( $value[ 'state' ] ) || !\in_array( $value[ 'state' ], [ 'begin', 'end' ] ) ) {
						$valid = new \WP_Error( 'Invalid context state.' );
					}
					elseif ( $value[ 'state' ] === 'end' && !isset( $value[ 'success' ] ) ) {
						$valid = new \WP_Error( 'Invalid end state data.' );
					}
					elseif ( !empty( \array_diff_key( $value, \array_flip( [ 'state', 'success' ] ) ) ) ) {
						$valid = new \WP_Error( 'Invalid signal data keys.' );
					}
				}
				break;
			default:
				$valid = parent::customValidateRequestArg( $value, $request, $reqArgKey );
				break;
		}
		return $valid;
	}

	protected function getRouteArgsCustom() :array {
		return [
			'context'     => [
				'description' => 'Primary Signal Context ',
				'type'        => 'string',
				'enum'        => [
					'backup',
				],
				'required'    => true,
			],
			'signal_data' => [
				'description' => 'Signal Data',
				'type'        => 'object',
				'required'    => true,
			],
		];
	}

	private function enumContexts() :array {
		return [
			'backup',
		];
	}
}