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
				$possible = self::con()->comps->scans->getScanSlugs();
				$value = \array_intersect( $possible, $value );
				if ( empty( $value ) ) {
					$value = $possible;
				}
				break;

			default:
				return parent::customSanitizeRequestArg( $value, $request, $reqArgKey );
		}

		return $value;
	}

	protected function customValidateRequestArg( $value, \WP_REST_Request $request, string $reqArgKey ) {

		switch ( $reqArgKey ) {

			case 'scan_slugs':
				$possible = self::con()->comps->scans->getScanSlugs();
				$slugsSent = \array_filter( \is_array( $value ) ? $value : \explode( ',', $value ) );
				if ( !empty( $slugsSent ) && \count( \array_diff( $slugsSent, $possible ) ) > 0 ) {
					throw new \Exception( sprintf( 'Invalid scan slugs provided. Please only supply: %s', \implode( ', ', $possible ) ) );
				}
				break;

			default:
				return parent::customValidateRequestArg( $value, $request, $reqArgKey );
		}

		return $value;
	}
}