<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\Investigation;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation\InvestigationSubjectWheres;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class InvestigationSubjectWheresTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'esc_sql' )->alias( fn( string $value ) :string => \str_replace( "'", "\\'", $value ) );
	}

	public function testUserColumnWhereUsesPositiveUid() :void {
		$wheres = InvestigationSubjectWheres::forUserColumn( '`req`.`uid`', 42 );

		$this->assertSame( [ '`req`.`uid`=42' ], $wheres );
	}

	public function testUserColumnWhereRejectsInvalidUid() :void {
		$this->assertSame( [ '1=0' ], InvestigationSubjectWheres::forUserColumn( '`req`.`uid`', 0 ) );
	}

	public function testAssetSlugWhereEscapesUnsafeSlug() :void {
		$wheres = InvestigationSubjectWheres::forAssetSlug( "my-plugin' OR 1=1 --" );

		$this->assertCount( 2, $wheres );
		$this->assertStringContainsString( "`meta_key`='ptg_slug'", $wheres[ 0 ] );
		$this->assertStringContainsString( "\\'", $wheres[ 1 ] );
		$this->assertStringNotContainsString( "`meta_value`='my-plugin' OR 1=1 --'", $wheres[ 1 ] );
	}

	public function testAssetSlugWhereAllowsSpaces() :void {
		$wheres = InvestigationSubjectWheres::forAssetSlug( 'my plugin/main file.php' );

		$this->assertCount( 2, $wheres );
		$this->assertStringContainsString( "`meta_value`='my plugin/main file.php'", $wheres[ 1 ] );
	}

	public function testAssetSlugWhereReturnsImpossibleQueryForEmptySlug() :void {
		$this->assertSame( [ '1=0' ], InvestigationSubjectWheres::forAssetSlug( '' ) );
	}

	public function testCoreResultsWhereHasExpectedClauses() :void {
		$wheres = InvestigationSubjectWheres::forCoreResults();

		$this->assertCount( 2, $wheres );
		$this->assertStringContainsString( "`meta_key`='is_in_core'", $wheres[ 0 ] );
		$this->assertStringContainsString( "`meta_value`=1", $wheres[ 1 ] );
	}

	public function testPluginActivityWheresIncludeEventFamilyAndPluginMetaMatching() :void {
		$wheres = InvestigationSubjectWheres::forPluginActivitySubject( 'akismet/akismet.php', 'wp_activity_meta' );

		$this->assertCount( 2, $wheres );
		$this->assertSame( "`log`.`event_slug` LIKE 'plugin_%'", $wheres[ 0 ] );
		$this->assertStringContainsString( "FROM `wp_activity_meta` as `meta_plugin`", $wheres[ 1 ] );
		$this->assertStringContainsString( "`meta_plugin`.`meta_key`='plugin'", $wheres[ 1 ] );
		$this->assertStringContainsString( "`meta_plugin`.`meta_value`='akismet/akismet.php'", $wheres[ 1 ] );
		$this->assertStringContainsString( "FROM `wp_activity_meta` as `meta_plugin_file`", $wheres[ 1 ] );
		$this->assertStringContainsString( "`log`.`event_slug`='plugin_file_edited'", $wheres[ 1 ] );
		$this->assertStringContainsString( "`meta_plugin_file`.`meta_key`='file'", $wheres[ 1 ] );
		$this->assertStringContainsString( "`meta_plugin_file`.`meta_value` LIKE '%akismet/akismet.php%'", $wheres[ 1 ] );
		$this->assertStringContainsString( "`meta_plugin_file`.`meta_value` LIKE '%akismet/%'", $wheres[ 1 ] );
		$this->assertStringNotContainsString( "`meta_key` NOT IN ('uid','audit_count')", $wheres[ 1 ] );
	}

	public function testThemeActivityWheresIncludeEventFamilyAndThemeMetaMatching() :void {
		$wheres = InvestigationSubjectWheres::forThemeActivitySubject( 'twentytwentyfive', 'wp_activity_meta' );

		$this->assertCount( 2, $wheres );
		$this->assertSame( "`log`.`event_slug` LIKE 'theme_%'", $wheres[ 0 ] );
		$this->assertStringContainsString( "FROM `wp_activity_meta` as `meta_theme`", $wheres[ 1 ] );
		$this->assertStringContainsString( "`meta_theme`.`meta_key`='theme'", $wheres[ 1 ] );
		$this->assertStringContainsString( "`meta_theme`.`meta_value`='twentytwentyfive'", $wheres[ 1 ] );
		$this->assertStringContainsString( "FROM `wp_activity_meta` as `meta_theme_file`", $wheres[ 1 ] );
		$this->assertStringContainsString( "`log`.`event_slug`='theme_file_edited'", $wheres[ 1 ] );
		$this->assertStringContainsString( "`meta_theme_file`.`meta_key`='file'", $wheres[ 1 ] );
		$this->assertStringContainsString( "`meta_theme_file`.`meta_value` LIKE '%twentytwentyfive/%'", $wheres[ 1 ] );
		$this->assertStringNotContainsString( "`meta_key` NOT IN ('uid','audit_count')", $wheres[ 1 ] );
		$this->assertStringNotContainsString( "`meta_theme_file`.`meta_value` LIKE '%twentytwentyfive%'", $wheres[ 1 ] );
	}

	public function testActivityFileFallbackEscapesSqlLikeWildcardsInTokens() :void {
		$wheres = InvestigationSubjectWheres::forPluginActivitySubject( 'foo_%/main.php', 'wp_activity_meta' );

		$this->assertCount( 2, $wheres );
		$this->assertStringContainsString( "`meta_plugin_file`.`meta_value` LIKE '%foo\\_\\%/main.php%'", $wheres[ 1 ] );
	}

	public function testThemeActivityFallbackUsesDirOnlyTokensWhenSubjectContainsPath() :void {
		$wheres = InvestigationSubjectWheres::forThemeActivitySubject( 'theme-dir/style.css', 'wp_activity_meta' );

		$this->assertCount( 2, $wheres );
		$this->assertStringContainsString( "`meta_theme_file`.`meta_value` LIKE '%theme-dir/%'", $wheres[ 1 ] );
		$this->assertStringContainsString( "`meta_theme_file`.`meta_value` LIKE '%/theme-dir/%'", $wheres[ 1 ] );
		$this->assertStringNotContainsString( "`meta_theme_file`.`meta_value` LIKE '%theme-dir/style.css/%'", $wheres[ 1 ] );
	}

	public function testCoreActivityWheresIncludeExpectedCoreAndWpOptionEvents() :void {
		$wheres = InvestigationSubjectWheres::forCoreActivitySubject();

		$this->assertCount( 1, $wheres );
		$this->assertStringContainsString( "`log`.`event_slug` LIKE 'core_%'", $wheres[ 0 ] );
		$this->assertStringContainsString( "`log`.`event_slug`='permalinks_structure'", $wheres[ 0 ] );
		$this->assertStringContainsString( "`log`.`event_slug` LIKE 'wp_option_%'", $wheres[ 0 ] );
	}

	public function testActivitySubjectWhereRejectsUnsupportedSubjectType() :void {
		$this->assertSame(
			[ '1=0' ],
			InvestigationSubjectWheres::forActivitySubject( 'request', 'abc', 'wp_activity_meta' )
		);
	}
}
