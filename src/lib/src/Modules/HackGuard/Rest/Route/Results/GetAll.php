<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Route\Results;

class GetAll extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Route\Base {

	protected function getRouteArgsCustom() :array {
		return [
			'scan_slugs'        => $this->getRouteArgSchema( 'scan_slugs' ),
			'filter_item_state' => [
				'description' => '[Filter] Comma-separated scan item states to include.',
				'type'        => 'string',
				'required'    => false,
				'pattern'     => sprintf( '^((%s),?)+$', \implode( '|', [
					'is_checksumfail',
					'is_unrecognised',
					'is_mal',
					'is_missing',
					'is_abandoned',
					'is_vulnerable',
				] ) ),
			],
		];
	}

	public function getRoutePath() :string {
		return '/scan_results';
	}

	protected function getRequestProcessorClass() :string {
		return \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Request\Results\GetAll::class;
	}
}