<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route;

use FernleafSystems\WorpdriveClient\Utility\EnumTypes;

class Download extends BaseWorpdrive {

	public function getRoutePath() :string {
		return '/download';
	}

	protected function getRouteArgsCustom() :array {
		return [
			'download_type'    => [
				'description' => 'Download Type',
				'type'        => 'string',
				'enum'        => ( new EnumTypes() )->downloads(),
				'required'    => true,
			],
		];
	}
}
