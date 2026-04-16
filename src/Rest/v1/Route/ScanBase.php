<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route;

abstract class ScanBase extends Base {

	public function getRoutePathPrefix() :string {
		return '/';
	}

	protected function getRouteArgSchema( string $key ) :array {
		switch ( $key ) {
			case 'scan_slugs':
				$sch = [
					'title'       => 'Scan Slugs',
					'description' => sprintf( 'Comma-separated scan slugs to include (allowed: %s).',
						\implode( ', ', self::con()->comps->scans->getScanSlugs() ) ),
					'type'        => 'array',
					'required'    => false,
				];
				break;

			default:
				$sch = parent::getRouteArgSchema( $key );
				break;
		}
		return $sch;
	}

	protected function customSanitizeRequestArg( $value, \WP_REST_Request $request, string $reqArgKey ) {

		switch ( $reqArgKey ) {

			case 'scan_slugs':
				$value = \array_values( \array_unique( \array_filter( \array_map(
					static fn( $slug ) :string => \trim( (string)$slug ),
					\is_array( $value ) ? $value : \explode( ',', (string)$value )
				), static fn( string $slug ) :bool => $slug !== '' ) ) );
				break;

			default:
				return parent::customSanitizeRequestArg( $value, $request, $reqArgKey );
		}

		return $value;
	}
}
