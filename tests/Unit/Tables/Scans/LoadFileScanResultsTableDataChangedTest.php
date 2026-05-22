<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\{
	Retrieve\RetrieveItems,
	ScanResultVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\{
	ResultItem,
	ResultsSet
};
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans\LoadFileScanResultsTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class LoadFileScanResultsTableDataChangedTest extends BaseUnitTest {

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function testRunTracksStaleCleanupChangesAndReloadsRows() :void {
		$calls = $this->installAfsCleaner( [ true ], [ 1 ] );
		$loader = $this->newLoader( $calls );

		$this->assertSame( [], $loader->run() );
		$this->assertTrue( $loader->hasScanResultsChanged() );
		$this->assertSame( 1, $calls->cleanCalls );
		$this->assertSame( 2, $calls->retrieveCalls );
	}

	public function testRunResetsChangedFlagWhenLaterRowsAreUnchanged() :void {
		$calls = $this->installAfsCleaner( [ true, false ], [ 1, 2, 3 ] );
		$loader = $this->newLoader( $calls );

		$loader->run();
		$this->assertTrue( $loader->hasScanResultsChanged() );

		$loader->run();
		$this->assertFalse( $loader->hasScanResultsChanged() );
		$this->assertSame( 2, $calls->cleanCalls );
		$this->assertSame( 3, $calls->retrieveCalls );
	}

	private function installAfsCleaner( array $cleanResults, array $itemRetrieveCalls ) :\stdClass {
		$calls = (object)[
			'cleanCalls'       => 0,
			'retrieveCalls'    => 0,
			'cleanResults'     => $cleanResults,
			'itemRetrieveCalls' => $itemRetrieveCalls,
		];
		UnitTestControllerFactory::install(
			null,
			null,
			(object)[
				'comps' => (object)[
					'scans' => new class( $calls ) {
						private \stdClass $calls;

						public function __construct( \stdClass $calls ) {
							$this->calls = $calls;
						}

						public function AFS() :object {
							return new class( $this->calls ) {
								private \stdClass $calls;

								public function __construct( \stdClass $calls ) {
									$this->calls = $calls;
								}

								public function cleanStaleResultItem( ResultItem $item ) :bool {
									$this->calls->cleanCalls++;
									return \array_shift( $this->calls->cleanResults ) ?? false;
								}
							};
						}
					},
				],
			]
		);

		return $calls;
	}

	private function newLoader( \stdClass $calls ) :LoadFileScanResultsTableData {
		return new class( $calls ) extends LoadFileScanResultsTableData {
			private \stdClass $calls;

			public function __construct( \stdClass $calls ) {
				$this->calls = $calls;
			}

			protected function getRecordRetriever() :RetrieveItems {
				return new class( $this->calls ) extends RetrieveItems {
					private \stdClass $calls;

					public function __construct( \stdClass $calls ) {
						$this->calls = $calls;
					}

					public function retrieveForResultsTables( ?array $options = null ) :ResultsSet {
						$this->calls->retrieveCalls++;
						if ( !\in_array( $this->calls->retrieveCalls, $this->calls->itemRetrieveCalls, true ) ) {
							return new ResultsSet();
						}

						$item = new ResultItem();
						$item->VO = new ScanResultVO();
						$item->is_mal = false;
						return ( new ResultsSet() )->addItem( $item );
					}
				};
			}

			protected function getDataFromItem( ResultItem $item ) :array {
				return [ 'rid' => 1 ];
			}
		};
	}
}
