<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Enum;

class DownloadTypes {

	public function allTypes() :array {
		return [
			'hashless_map_progress',
			'recent_map_progress',
			'full_map_progress',
			'hashless_map_db',
			'recent_map_db',
			'full_map_db',
			'files_zip',
			'db_exports_zip',
		];
	}
}