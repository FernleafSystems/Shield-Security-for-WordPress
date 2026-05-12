<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\Investigation;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation\InvestigationActivityLogTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class InvestigationActivityLogLinksTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'esc_html' )->alias( static fn( $text ) :string => (string)$text );
		Functions\when( 'esc_attr' )->alias( static fn( $text ) :string => (string)$text );
		Functions\when( 'esc_url' )->alias( static fn( $text ) :string => (string)$text );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function testAppendsPluginAndThemeInvestigateLinksToRowMessage() :void {
		$record = new LogRecord();
		$record->meta_data = [
			'plugin' => 'akismet/akismet.php',
			'theme'  => 'twentytwentyfive',
		];

		$row = $this->invokeAppendLinks(
			new InvestigationActivityLogTableData(),
			[
				'message' => 'Base message',
			],
			$record
		);

		$message = (string)( $row[ 'message' ] ?? '' );
		$this->assertStringContainsString( 'Base message', $message );
		$this->assertStringContainsString( 'Investigate Plugin', $message );
		$this->assertStringContainsString( 'plugin_slug=akismet/akismet.php', $message );
		$this->assertStringContainsString( 'Investigate Theme', $message );
		$this->assertStringContainsString( 'theme_slug=twentytwentyfive', $message );
		$this->assertStringContainsString( 'small mt-1', $message );
	}

	public function testNoInvestigateLinksAppendedWhenAssetMetaMissing() :void {
		$record = new LogRecord();
		$record->meta_data = [];

		$row = $this->invokeAppendLinks(
			new InvestigationActivityLogTableData(),
			[
				'message' => 'Base message',
			],
			$record
		);

		$this->assertSame( 'Base message', (string)( $row[ 'message' ] ?? '' ) );
	}

	private function invokeAppendLinks( InvestigationActivityLogTableData $tableData, array $row, LogRecord $record ) :array {
		$method = new \ReflectionMethod( InvestigationActivityLogTableData::class, 'appendAssetInvestigateLinks' );
		$method->setAccessible( true );
		return $method->invoke( $tableData, $row, $record );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function investigateByPlugin( string $slug = '' ) :string {
				return '/admin/activity/by_plugin?plugin_slug='.$slug;
			}

			public function investigateByTheme( string $slug = '' ) :string {
				return '/admin/activity/by_theme?theme_slug='.$slug;
			}
		};
		PluginControllerInstaller::install( $controller );
	}
}
