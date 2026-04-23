<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansCheck;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Db;

class ScansCheckTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( '__' )->returnArg();
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_exec_reports_failed_started_scan_with_failure_message() :void {
		$failureMessage = 'producer failure detail';
		$this->installController( $failureMessage );
		ServicesState::installItems( [
			'service_wpdb' => new class extends Db {
				public function getVar( $sql ) {
					unset( $sql );
					return '';
				}
			},
		] );

		$action = new ScansCheck( [
			'scan_ids' => [ 21 ],
		] );
		$method = new \ReflectionMethod( ScansCheck::class, 'exec' );
		$method->setAccessible( true );
		$method->invoke( $action );

		$payload = $action->response()->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertTrue( $payload[ 'failed' ] ?? false );
		$this->assertSame( $failureMessage, $payload[ 'failure_message' ] ?? '' );
		$this->assertNotSame( '', (string)( $payload[ 'vars' ][ 'progress_html' ] ?? '' ) );
	}

	private function installController( string $failureMessage ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
			'scans' => new class( $failureMessage ) {
				public function __construct( private string $failureMessage ) {
				}

				public function getTable() :string {
					return 'shield_scans';
				}

				public function getQuerySelector() :object {
					return new class( $this->failureMessage ) {
						public function __construct( private string $failureMessage ) {
						}

						public function byId( int $scanID ) :object {
							return (object)[
								'id' => $scanID,
								'status' => 'failed',
								'meta' => [
									'last_error' => $this->failureMessage,
								],
							];
						}

						public function filterByNotFinished() :self {
							return $this;
						}

						public function addWhereIn( string $column, array $values ) :self {
							unset( $column, $values );
							return $this;
						}

						public function addColumnToSelect( string $column ) :self {
							unset( $column );
							return $this;
						}

						public function setIsDistinct( bool $isDistinct ) :self {
							unset( $isDistinct );
							return $this;
						}

						public function queryWithResult() :array {
							return [];
						}
					};
				}
			},
		];
		$controller->comps = (object)[
			'scans' => new class {
				public function getScanCon( string $slug ) :object {
					unset( $slug );
					return new class {
						public function getScanName() :string {
							return 'Unused';
						}
					};
				}
			},
			'scans_queue' => new class {
				public function getScansRunningStates() :array {
					return [ 'afs' => false, 'wpv' => false, 'apc' => false ];
				}

				public function getScanJobProgress() :float {
					return 0.2;
				}
			},
		];
		$controller->action_router = new class {
			public array $renderData = [];

			public function render( string $renderClass, array $data ) :string {
				unset( $renderClass );
				$this->renderData = $data;
				return 'rendered';
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}
