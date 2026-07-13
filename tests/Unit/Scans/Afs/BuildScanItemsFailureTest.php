<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\{
	BuildScanItems,
	ScanActionVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class BuildScanItemsFailureTest extends BaseUnitTest {

	public function test_missing_scan_root_failure_propagates() :void {
		$missingRoot = \sys_get_temp_dir().'/shield-missing-scan-root-'.\uniqid();
		$action = new ScanActionVO();

		$this->expectException( \UnexpectedValueException::class );

		( new BuildScanItemsMissingRoot( $missingRoot ) )
			->setScanActionVO( $action )
			->run();
	}
}

class BuildScanItemsMissingRoot extends BuildScanItems {

	private string $missingRoot;

	public function __construct( string $missingRoot ) {
		$this->missingRoot = $missingRoot;
	}

	protected function preBuild() :void {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		$action->scope_type = 'plugin';
		$action->scan_root_dirs = [
			$this->missingRoot => 0,
		];
		$action->valid_files = [];
		$action->paths_whitelisted = [];
		$action->file_exts = [ 'php' ];
	}
}
