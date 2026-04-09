<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;

class ScanResultsFileLocker extends ScanResultsBase {

	public const SLUG = 'scan_results_file_locker';

	protected function countResults() :int {
		return \count( ( new LoadFileLocks() )->withProblems() );
	}

	public function title() :string {
		return __( 'File Locker', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "Locked files don't appear to have any changes that need review.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( 'There appear to be locked files with changes that need review.', 'wp-simple-firewall' );
	}
}
