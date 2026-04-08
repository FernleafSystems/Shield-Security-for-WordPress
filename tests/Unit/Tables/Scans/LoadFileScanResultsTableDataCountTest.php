<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\{
	RetrieveCount,
	RetrieveItems
};
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans\LoadFileScanResultsTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class LoadFileScanResultsTableDataCountTest extends BaseUnitTest {

	public function testCountAllUsesDisplayOptionsCounterWithoutTouchingRetrievePath() :void {
		[ $loader, $calls ] = $this->createLoader();
		$loader->results_display_options = [
			'include_ignored'  => true,
			'include_repaired' => true,
			'include_deleted'  => false,
			'ignored_only'     => false,
		];

		$this->assertSame( 21, $loader->countAll() );
		$this->assertSame( 0, $calls->retrieveCalls );
		$this->assertSame( 0, $calls->defaultCountCalls );
		$this->assertSame( 1, $calls->displayCountCalls );
		$this->assertSame(
			[
				'include_ignored'  => true,
				'include_repaired' => true,
				'include_deleted'  => false,
				'ignored_only'     => false,
			],
			$calls->lastDisplayOptions
		);
	}

	public function testCountAllUsesDefaultCounterWhenNoDisplayOptionsProvided() :void {
		[ $loader, $calls ] = $this->createLoader();

		$this->assertSame( 13, $loader->countAll() );
		$this->assertSame( 0, $calls->retrieveCalls );
		$this->assertSame( 1, $calls->defaultCountCalls );
		$this->assertSame( 0, $calls->displayCountCalls );
		$this->assertNull( $calls->lastDisplayOptions );
	}

	/**
	 * @return array{0:LoadFileScanResultsTableData,1:\stdClass}
	 */
	private function createLoader() :array {
		$calls = (object)[
			'retrieveCalls'     => 0,
			'defaultCountCalls' => 0,
			'displayCountCalls' => 0,
			'lastDisplayOptions'=> null,
		];

		$loader = new class( $calls ) extends LoadFileScanResultsTableData {

			private \stdClass $calls;

			public function __construct( \stdClass $calls ) {
				$this->calls = $calls;
			}

			protected function getRecordCounter() :RetrieveCount {
				return new class( $this->calls ) extends RetrieveCount {

					private \stdClass $calls;

					public function __construct( \stdClass $calls ) {
						$this->calls = $calls;
					}

					public function count( int $countContext = self::CONTEXT_ACTIVE_PROBLEMS ) :int {
						$this->calls->defaultCountCalls++;
						return 13;
					}

					public function countForResultsDisplay( array $resultsDisplayOptions = [] ) :int {
						$this->calls->displayCountCalls++;
						$this->calls->lastDisplayOptions = $resultsDisplayOptions;
						return 21;
					}
				};
			}

			protected function getRecordRetriever() :RetrieveItems {
				$this->calls->retrieveCalls++;
				throw new \RuntimeException( 'countAll() should not touch the row retrieval path.' );
			}
		};

		return [ $loader, $calls ];
	}
}
