<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;

class ScanEnabledFileLockerHtaccess extends ScanEnabledFileLockerBase {

	public const FILE_LOCKER_FILE = '.htaccess';
	public const FILE_LOCKER_FILE_KEY = 'root_htaccess';
	public const WEIGHT = 5;

	protected function isApplicable() :bool {
		return Services::WpFs()->isAccessibleFile( path_join( ABSPATH, '.htaccess' ) );
	}
}