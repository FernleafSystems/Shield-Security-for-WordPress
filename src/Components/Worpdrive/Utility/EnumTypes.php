<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Utility;

class EnumTypes {

	public function downloads() :array {
		return [
			'hashless_map_progress',
			'recent_map_progress',
			'full_map_progress',
			'hashless_map_db',
			'recent_map_db',
			'full_map_db',
			'files_zip',
			'db_exports_zip',
			'db_schema_zip',
		];
	}

	public function filesystemMaps() :array {
		return [
			'map',
			'hashless',
			'recent',
		];
	}
}