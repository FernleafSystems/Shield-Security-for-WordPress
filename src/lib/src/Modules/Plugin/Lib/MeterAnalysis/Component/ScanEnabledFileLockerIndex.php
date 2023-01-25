<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;

class ScanEnabledFileLockerIndex extends ScanEnabledFileLockerBase {

	public const FILE_LOCKER_FILE = 'index.php';
	public const FILE_LOCKER_FILE_KEY = 'root_index';
	public const WEIGHT = 5;

	protected function isApplicable() :bool {
		return Services::WpFs()->isAccessibleFile( path_join( ABSPATH, 'index.php' ) );
	}
}