<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildLatestCompletedScanTimestamps;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops\Record as ScanRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class BuildLatestCompletedScanTimestampsTest extends BaseUnitTest {

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_requests_latest_completed_full_runs_only() :void {
		$calls = [];

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
			'scans' => new class( $calls ) {
				private array $calls;

				public function __construct( array &$calls ) {
					$this->calls = &$calls;
				}

				public function getQuerySelector() :object {
					return new class( $this->calls ) {
						private array $calls;
						private array $current = [];

						public function __construct( array &$calls ) {
							$this->calls = &$calls;
						}

						public function filterByScan( string $scan ) :self {
							$this->current['scan'] = $scan;
							return $this;
						}

						public function filterByStatus( string $status ) :self {
							$this->current['status'] = $status;
							return $this;
						}

						public function addWhereEquals( string $column, string $value ) :self {
							$this->current[ $column ] = $value;
							return $this;
						}

						public function filterByFinished() :self {
							$this->current['finished'] = true;
							return $this;
						}

						public function setOrderBy( string $column, string $direction = 'DESC', bool $escape = false ) :self {
							$this->current['order_by'] = $column;
							$this->current['order_dir'] = $direction;
							$this->current['order_escape'] = $escape;
							return $this;
						}

						public function first() :ScanRecord {
							$record = new ScanRecord();
							$record->finished_at = 1700000000;
							$this->calls[] = $this->current;
							$this->current = [];
							return $record;
						}
					};
				}
			},
		];

		PluginControllerInstaller::install( $controller );

		$result = ( new BuildLatestCompletedScanTimestamps() )->build();

		$this->assertSame( [
			'malware' => 1700000000,
			'vulnerabilities' => 1700000000,
			'abandoned' => 1700000000,
			'core_files' => 1700000000,
			'plugin_files' => 1700000000,
			'theme_files' => 1700000000,
		], $result );
		$this->assertCount( 3, $calls );
		$this->assertSame( 'completed', $calls[ 0 ][ 'status' ] ?? null );
		$this->assertSame( 'full', $calls[ 0 ][ 'scope_type' ] ?? null );
		$this->assertTrue( $calls[ 0 ][ 'finished' ] ?? false );
	}
}
