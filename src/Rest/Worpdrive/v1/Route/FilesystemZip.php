<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route;

class FilesystemZip extends BaseWorpdrive {

	public function getRoutePath() :string {
		return '/filesystem/zip';
	}

	protected function getRouteArgsCustom() :array {
		return [
			'file_paths' => [
				'description' => 'All file paths to be zipped',
				'type'        => 'object',
				'required'    => true,
			],
			'dir'             => [
				'description' => 'Root dir for all relative file paths',
				'type'        => 'string',
				'required'    => true,
			],
		];
	}
}