<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueScanResultScopeStateBuilder,
	ScanResultsDisplayOptions
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveCount;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActionsQueueScanResultScopeStateBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		Functions\when( 'number_format_i18n' )->alias( static fn( int $number ) :string => (string)$number );
	}

	public function test_active_only_current_count_reuses_active_count() :void {
		[ $builder, $calls ] = $this->newBuilder();

		$state = $builder->buildForActionScope( 'plugin', 'akismet/akismet.php', ( new ScanResultsDisplayOptions() )->activeOnly(), true );

		$this->assertSame( 3, $state[ 'current_count' ] );
		$this->assertSame( 2, \count( $calls->displayOptions ) );
		$this->assertSame( ActionsQueueScanResultScopeStateBuilder::MODE_HIDDEN_IGNORED, $state[ 'display_notice' ][ 'mode' ] );
		$this->assertSame( 2, $state[ 'display_notice' ][ 'ignored_count' ] );
	}

	public function test_ignored_only_current_count_reuses_ignored_count() :void {
		[ $builder, $calls ] = $this->newBuilder();

		$state = $builder->buildForActionScope( 'plugin', 'akismet/akismet.php', ( new ScanResultsDisplayOptions() )->ignoredOnly(), true );

		$this->assertSame( 2, $state[ 'current_count' ] );
		$this->assertSame( 2, \count( $calls->displayOptions ) );
		$this->assertSame( ActionsQueueScanResultScopeStateBuilder::MODE_SHOWING_IGNORED, $state[ 'display_notice' ][ 'mode' ] );
	}

	public function test_active_and_ignored_current_count_reuses_active_plus_ignored_counts() :void {
		[ $builder, $calls ] = $this->newBuilder();

		$state = $builder->buildForActionScope( 'plugin', 'akismet/akismet.php', ( new ScanResultsDisplayOptions() )->activeAndIgnored(), true );

		$this->assertSame( 5, $state[ 'current_count' ] );
		$this->assertSame( 2, \count( $calls->displayOptions ) );
		$this->assertSame( ActionsQueueScanResultScopeStateBuilder::MODE_INCLUDING_IGNORED, $state[ 'display_notice' ][ 'mode' ] );
	}

	public function test_custom_display_options_use_counter_for_current_count() :void {
		[ $builder, $calls ] = $this->newBuilder();
		$options = [
			'include_ignored'  => false,
			'include_repaired' => false,
			'include_deleted'  => true,
			'ignored_only'     => false,
		];

		$state = $builder->buildForActionScope( 'plugin', 'akismet/akismet.php', $options, true );

		$this->assertSame( 7, $state[ 'current_count' ] );
		$this->assertSame( 3, \count( $calls->displayOptions ) );
		$this->assertSame( $options, $calls->displayOptions[ 2 ] );
	}

	public function test_notice_stays_hidden_without_display_notice_flag() :void {
		[ $builder ] = $this->newBuilder();

		$state = $builder->buildForActionScope( 'plugin', 'akismet/akismet.php', ( new ScanResultsDisplayOptions() )->activeOnly(), false );

		$this->assertSame( ActionsQueueScanResultScopeStateBuilder::MODE_NONE, $state[ 'display_notice' ][ 'mode' ] );
		$this->assertFalse( $state[ 'display_notice' ][ 'is_visible' ] );
		$this->assertSame( 0, $state[ 'display_notice' ][ 'ignored_count' ] );
	}

	public function test_notice_stays_hidden_when_scope_has_no_ignored_results() :void {
		[ $builder ] = $this->newBuilder( 3, 0 );

		$state = $builder->buildForActionScope( 'plugin', 'akismet/akismet.php', ( new ScanResultsDisplayOptions() )->activeOnly(), true );

		$this->assertSame( ActionsQueueScanResultScopeStateBuilder::MODE_NONE, $state[ 'display_notice' ][ 'mode' ] );
		$this->assertFalse( $state[ 'display_notice' ][ 'is_visible' ] );
	}

	private function newBuilder( int $activeCount = 3, int $ignoredCount = 2, int $customCount = 7 ) :array {
		$calls = (object)[
			'displayOptions' => [],
		];

		$builder = new class( $calls, $activeCount, $ignoredCount, $customCount ) extends ActionsQueueScanResultScopeStateBuilder {
			private \stdClass $calls;
			private int $activeCount;
			private int $ignoredCount;
			private int $customCount;

			public function __construct( \stdClass $calls, int $activeCount, int $ignoredCount, int $customCount ) {
				parent::__construct();
				$this->calls = $calls;
				$this->activeCount = $activeCount;
				$this->ignoredCount = $ignoredCount;
				$this->customCount = $customCount;
			}

			protected function buildCounterForScope( array $scope ) :RetrieveCount {
				return new class( $this->calls, $this->activeCount, $this->ignoredCount, $this->customCount ) extends RetrieveCount {
					private \stdClass $calls;
					private int $activeCount;
					private int $ignoredCount;
					private int $customCount;

					public function __construct( \stdClass $calls, int $activeCount, int $ignoredCount, int $customCount ) {
						$this->calls = $calls;
						$this->activeCount = $activeCount;
						$this->ignoredCount = $ignoredCount;
						$this->customCount = $customCount;
					}

					public function countForResultsDisplay( array $options = [] ) :int {
						$this->calls->displayOptions[] = $options;
						$displayOptions = new ScanResultsDisplayOptions();

						if ( $options === $displayOptions->activeOnly() ) {
							return $this->activeCount;
						}

						if ( $options === $displayOptions->ignoredOnly() ) {
							return $this->ignoredCount;
						}

						return $this->customCount;
					}
				};
			}
		};

		return [ $builder, $calls ];
	}
}
