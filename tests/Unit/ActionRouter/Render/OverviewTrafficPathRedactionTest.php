<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\OverviewTraffic;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Request;

class OverviewTrafficPathRedactionTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'apply_filters' )->alias( static fn( string $tag, $value ) => $value );
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_request' => new class extends Request {
				public function carbon( $setTimezone = false, bool $userLocale = true ) :Carbon {
					return Carbon::createFromTimestampUTC( 1713278100 );
				}
			},
		] );

		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function ipAnalysis( string $ip ) :string {
				return '/ip/'.$ip;
			}
		};
		PluginControllerInstaller::install( $controller );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_overview_log_row_uses_redacted_display_path() :void {
		$row = $this->invokeNonPublicMethod( new OverviewTraffic(), 'buildLogRow', [
			$this->record( '/wp-login.php', 'key=reset-secret&reauth=1' ),
		] );

		$this->assertSame( 'redacted', $this->queryValue( $row[ 'path' ], 'key' ) );
		$this->assertSame( '1', $this->queryValue( $row[ 'path' ], 'reauth' ) );
		$this->assertStringNotContainsString( 'reset-secret', $row[ 'path' ] );
	}

	private function record( string $path, string $query ) :LogRecord {
		$record = new LogRecord();
		$record->ip = '198.51.100.19';
		$record->created_at = 1713278100;
		$record->path = $path;
		$record->meta = [
			'query' => $query,
		];
		return $record;
	}

	private function queryValue( string $displayPath, string $key ) :string {
		$query = [];
		\parse_str( (string)\parse_url( $displayPath, \PHP_URL_QUERY ), $query );
		$this->assertArrayHasKey( $key, $query );
		return (string)$query[ $key ];
	}
}
