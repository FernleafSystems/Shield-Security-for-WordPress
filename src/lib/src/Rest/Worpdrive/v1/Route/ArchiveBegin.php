<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route;

class ArchiveBegin extends BaseWorpdrive {

	public function getRoutePath() :string {
		return '/archive_begin';
	}

	protected function getRouteArgsCustom() :array {
		return [
			'archive_success' => [
				'description' => 'Was the last archive attempt a success?',
				'type'        => 'boolean',
				'required'    => true,
			],
		];
	}
}