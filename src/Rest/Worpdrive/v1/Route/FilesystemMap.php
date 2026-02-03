<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Utility\EnumTypes;

class FilesystemMap extends BaseWorpdrive {

	public function getRoutePath() :string {
		return sprintf( '/filesystem/(?P<type>%s)', \implode( '|', ( new EnumTypes() )->filesystemMaps() ) );
	}

	/**
	 * @inheritDoc
	 */
	protected function customValidateRequestArg( $value, \WP_REST_Request $request, string $reqArgKey ) {
		switch ( $reqArgKey ) {
			case 'file_exclusions':
				$valid = true;
				if ( !\is_array( $value ) ) {
					$valid = new \WP_Error( 'Not an array.' );
				}
				elseif ( \count( \array_diff_key( $value, \array_flip( [ 'contains', 'regex' ] ) ) ) > 0 ) {
					$valid = new \WP_Error( 'Invalid keys - Only have "contains" and "regex" exclusions permitted.' );
				}
				else {
					foreach ( [ 'contains', 'regex' ] as $excType ) {
						if ( isset( $value[ $excType ] ) ) {
							if ( !\is_array( $value[ $excType ] ) ) {
								$valid = new \WP_Error( sprintf( '"%s" not an array.', $excType ) );
								break;
							}
							elseif ( \count( \array_filter( $value[ $excType ], fn( $exc ) => !\is_string( $exc ) ) ) > 0 ) {
								$valid = new \WP_Error( 'Exclusions may only contain strings.' );
								break;
							}
						}
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
			'type'            => [
				'description' => 'Filesystem Map Type',
				'type'        => 'string',
				'enum'        => ( new EnumTypes() )->filesystemMaps(),
				'required'    => true,
			],
			'dir'             => [
				'description' => 'Root dir to begin mapping',
				'type'        => 'string',
				'required'    => true,
			],
			'file_exclusions' => [
				'description' => 'Filesystem map exclusions',
				'type'        => 'object',
				'required'    => true,
			],
			'newer_than_ts' => [
				'description' => 'Only maps files that are newer than this Timestamp (Unix Timestamp in seconds)',
				'type'        => 'integer',
				'default'     => 0,
				'required'    => false,
			],
		];
	}
}