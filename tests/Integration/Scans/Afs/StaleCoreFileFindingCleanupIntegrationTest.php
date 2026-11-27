<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	ServicesState,
	TestDataFactory
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Core\CoreFileHashes;

/**
 * Findings that no scan revisits, such as those stored under an older path spelling or in an area that's no longer
 * scanned, are still settled when their file is WordPress's own copy.
 */
class StaleCoreFileFindingCleanupIntegrationTest extends ShieldIntegrationTestCase {

	private const CORE_FILE = 'shield-stale-core-file.php';

	private const MODIFIED_CORE_FILE = 'shield-stale-modified-core-file.php';

	private const UNKNOWN_FILE = 'shield-stale-unknown-file.php';

	/**
	 * In WordPress's checksums, but scans exclude wp-content from core verification and report it as unidentified.
	 */
	private const WPCONTENT_CORE_FILE = 'wp-content/shield-stale-core-file.php';

	private const CORE_CONTENT = "<?php\n// core original\n";

	private array $servicesSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );

		\file_put_contents( ABSPATH.self::CORE_FILE, self::CORE_CONTENT );
		\file_put_contents( ABSPATH.self::MODIFIED_CORE_FILE, "<?php\n// changed\n" );
		\file_put_contents( ABSPATH.self::UNKNOWN_FILE, "<?php\n// unknown\n" );
		\file_put_contents( ABSPATH.self::WPCONTENT_CORE_FILE, self::CORE_CONTENT );

		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::mergeItems( [
			'service_corefilehashes' => new class( [
				self::CORE_FILE           => \md5( self::CORE_CONTENT ),
				self::MODIFIED_CORE_FILE  => \md5( self::CORE_CONTENT ),
				self::WPCONTENT_CORE_FILE => \md5( self::CORE_CONTENT ),
			] ) extends CoreFileHashes {

				private array $fixtureHashes;

				public function __construct( array $hashes ) {
					$this->fixtureHashes = $hashes;
				}

				public function getHashes() :array {
					return $this->fixtureHashes;
				}
			},
		] );
	}

	public function tear_down() {
		ServicesState::restore( $this->servicesSnapshot );
		foreach ( [ self::CORE_FILE, self::MODIFIED_CORE_FILE, self::UNKNOWN_FILE, self::WPCONTENT_CORE_FILE ] as $file ) {
			if ( \file_exists( ABSPATH.$file ) ) {
				\unlink( ABSPATH.$file );
			}
		}
		parent::tear_down();
	}

	/**
	 * @dataProvider provideFindings
	 * @param array<string,int> $meta
	 */
	public function test_finding_is_cleared_only_when_the_file_is_an_original_core_file(
		string $file,
		array $meta,
		bool $expectCleared
	) :void {
		$finding = TestDataFactory::insertAfsFileScanResultTracked(
			TestDataFactory::insertCompletedScan( 'afs' ),
			ABSPATH.$file,
			$meta
		);
		$item = ( new RetrieveItems() )->byID( (int)$finding[ 'result_item_id' ] );

		$this->assertSame( $expectCleared, self::con()->comps->scans->AFS()->cleanStaleResultItem( $item ) );

		$stored = self::con()->db_con->scan_result_items->getQuerySelector()->byId( (int)$finding[ 'result_item_id' ] );
		$this->assertSame( $expectCleared, (int)$stored->resolved_at > 0 );
		$this->assertSame( $expectCleared ? 'clean_rescan' : '', (string)$stored->resolution_reason );
	}

	public function test_resolved_finding_keeps_its_resolution() :void {
		$finding = TestDataFactory::insertAfsFileScanResultTracked(
			TestDataFactory::insertCompletedScan( 'afs' ),
			ABSPATH.self::CORE_FILE,
			[ 'is_in_core' => 1, 'is_unrecognised' => 1 ]
		);
		TestDataFactory::markScanResultItemResolved( (int)$finding[ 'result_item_id' ], 'deleted' );
		$item = ( new RetrieveItems() )->byID( (int)$finding[ 'result_item_id' ] );

		$this->assertFalse( self::con()->comps->scans->AFS()->cleanStaleResultItem( $item ) );

		$stored = self::con()->db_con->scan_result_items->getQuerySelector()->byId( (int)$finding[ 'result_item_id' ] );
		$this->assertSame( 'deleted', (string)$stored->resolution_reason );
	}

	public function provideFindings() :array {
		$rootUnidentified = [ 'is_in_core' => 1, 'is_in_wproot' => 1, 'is_unidentified' => 1 ];
		return [
			'root unidentified, original core file'     => [ self::CORE_FILE, $rootUnidentified, true ],
			'core unrecognised, original core file'     => [
				self::CORE_FILE,
				[ 'is_in_core' => 1, 'is_unrecognised' => 1 ],
				true,
			],
			'root unidentified, modified core file'     => [ self::MODIFIED_CORE_FILE, $rootUnidentified, false ],
			'root unidentified, not a core file'        => [ self::UNKNOWN_FILE, $rootUnidentified, false ],
			'root unidentified with malware, core file' => [
				self::CORE_FILE,
				\array_merge( $rootUnidentified, [ 'is_mal' => 1 ] ),
				false,
			],
			'wp-content unidentified, core file the scan excludes' => [
				self::WPCONTENT_CORE_FILE,
				[ 'is_in_core' => 1, 'is_in_wpcontent' => 1, 'is_unidentified' => 1 ],
				false,
			],
		];
	}
}
