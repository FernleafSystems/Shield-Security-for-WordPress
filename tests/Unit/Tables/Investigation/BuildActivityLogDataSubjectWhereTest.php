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
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation\BuildActivityLogData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	PluginStore
};

class BuildActivityLogDataSubjectWhereTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'esc_sql' )->alias( fn( string $value ) :string => \str_replace( "'", "\\'", $value ) );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function testPluginSubjectBuildsPluginFamilyWhereClauses() :void {
		$wheres = $this->buildSubjectWheres( 'plugin', 'akismet/akismet.php' );

		$this->assertCount( 2, $wheres );
		$this->assertSame( "`log`.`event_slug` LIKE 'plugin_%'", $wheres[ 0 ] );
		$this->assertStringContainsString( "`meta_plugin`.`meta_key`='plugin'", $wheres[ 1 ] );
		$this->assertStringContainsString( "`meta_plugin`.`meta_value`='akismet/akismet.php'", $wheres[ 1 ] );
	}

	public function testThemeSubjectBuildsThemeFamilyWhereClauses() :void {
		$wheres = $this->buildSubjectWheres( 'theme', 'twentytwentyfive' );

		$this->assertCount( 2, $wheres );
		$this->assertSame( "`log`.`event_slug` LIKE 'theme_%'", $wheres[ 0 ] );
		$this->assertStringContainsString( "`meta_theme`.`meta_key`='theme'", $wheres[ 1 ] );
		$this->assertStringContainsString( "`meta_theme`.`meta_value`='twentytwentyfive'", $wheres[ 1 ] );
	}

	public function testCoreSubjectBuildsCoreAndWpOptionWhereClause() :void {
		$wheres = $this->buildSubjectWheres( 'core', 'core' );

		$this->assertCount( 1, $wheres );
		$this->assertStringContainsString( "`log`.`event_slug` LIKE 'core_%'", $wheres[ 0 ] );
		$this->assertStringContainsString( "`log`.`event_slug`='permalinks_structure'", $wheres[ 0 ] );
		$this->assertStringContainsString( "`log`.`event_slug` LIKE 'wp_option_%'", $wheres[ 0 ] );
	}

	public function testUnsupportedSubjectReturnsImpossibleWhereClause() :void {
		$this->assertSame(
			[ '1=0' ],
			$this->buildSubjectWheres( 'request', 'x' )
		);
	}

	private function buildSubjectWheres( string $subjectType, string $subjectId ) :array {
		$builder = new class extends BuildActivityLogData {
			public function exposeGetSubjectWheres() :array {
				return $this->getSubjectWheres();
			}
		};
		$builder->setSubject( $subjectType, $subjectId );
		return $builder->exposeGetSubjectWheres();
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
			'activity_logs_meta' => new class {
				public function getTable() :string {
					return 'wp_activity_meta';
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}
}
