<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Route\RouteBase;

abstract class Base extends RouteBase {

	public function getRoutePathPrefix() :string {
		return '/';
	}

	protected function getRouteArgSchema( string $key ) :array {
		switch ( $key ) {
			case 'scan_slugs':
				$possible = $this->getCon()->getModule_HackGuard()->getScansCon()->getScanSlugs();
				$sch = [
					'title'       => 'Scan Slugs',
					'description' => sprintf( 'Comma-separated scan slugs to include (allowed: %s).', implode( ', ', $possible ) ),
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
				$possible = $this->getCon()->getModule_HackGuard()->getScansCon()->getScanSlugs();
				$value = array_intersect( $possible, $value );
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
				$possible = $this->getCon()->getModule_HackGuard()->getScansCon()->getScanSlugs();
				$slugsSent = array_filter( is_array( $value ) ? $value : explode( ',', $value ) );
				if ( !empty( $slugsSent ) && count( array_diff( $slugsSent, $possible ) ) > 0 ) {
					throw new \Exception( sprintf( 'Invalid scan slugs provided. Please only supply: %s', implode( ', ', $possible ) ) );
				}
				break;

			default:
				return parent::customValidateRequestArg( $value, $request, $reqArgKey );
		}

		return $value;
	}
}