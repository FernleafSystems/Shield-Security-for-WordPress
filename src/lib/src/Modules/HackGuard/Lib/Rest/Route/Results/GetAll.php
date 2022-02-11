<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Route\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Route\RouteBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Request\Results;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;

class GetAll extends RouteBase {

	protected function getRouteArgsCustom() :array {
		return [
			'filter_scan'       => [
				'description' => '[Filter] Comma-separated scan slugs include.',
				'type'        => 'string',
				'required'    => false,
			],
			'filter_item_state' => [
				'description' => '[Filter] Comma-separated scan item states to include.',
				'type'        => 'string',
				'required'    => false,
			],
		];
	}

	/**
	 * @param string|mixed $value
	 * @return \WP_Error|true
	 * @throws \Exception
	 */
	protected function customValidateRequestArg( $value, \WP_REST_Request $request, string $reqArgKey ) {

		switch ( $reqArgKey ) {

			case 'filter_scan':
				/** @var Options $opts */
				$opts = $this->getOptions();
				$possible = $opts->getScanSlugs();
				if ( count( array_diff( explode( ',', $value ), $possible ) ) ) {
					throw new \Exception( sprintf( 'Filter parameter (%s) contains invalid options. Available: %s.',
						'filter_scan', implode( ',', $possible ) ) );
				}
				break;

			case 'filter_item_state':
				$possible = [
					'is_checksumfail',
					'is_unrecognised',
					'is_mal',
					'is_missing',
					'is_abandoned',
					'is_vulnerable',
				];
				if ( count( array_diff( explode( ',', $value ), $possible ) ) ) {
					throw new \Exception( sprintf( 'Filter parameter (%s) contains invalid options. Available: %s.',
						'filter_item_state', implode( ',', $possible ) ) );
				}
				break;
		}
		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function customSanitizeRequestArg( $value, \WP_REST_Request $request, string $reqArgKey ) {

		switch ( $reqArgKey ) {

			case 'filter_scan':
			case 'filter_item_state':
				if ( is_string( $value ) ) {
					$value = strtolower( preg_replace( '#[^a-z_,]#i', '', $value ) );
				}
				break;
		}

		return $value;
	}

	public function getRoutePath() :string {
		return '/scan_results';
	}

	protected function getRequestProcessorClass() :string {
		return Results\GetAll::class;
	}
}