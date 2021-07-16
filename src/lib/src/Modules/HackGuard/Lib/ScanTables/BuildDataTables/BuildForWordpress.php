<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\BuildDataTables;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class BuildForWordpress extends BaseBuild {

	protected function getColumnsToDisplay() :array {
		return [
			'rid',
			'file_as_href',
			'status',
			'file_type',
			'detected',
			'actions',
		];
	}
}