<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Route\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Request\Results;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Route\Base;

class GetAll extends Base {

	protected function getRouteArgsCustom() :array {
		return [
			'filter_item_state' => [
				'description' => '[Filter] Comma-separated scan item states to include.',
				'type'        => 'string',
				'required'    => false,
				'pattern'     => sprintf( '^((%s),?)+$', implode( '|', [
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
		return Results\GetAll::class;
	}
}