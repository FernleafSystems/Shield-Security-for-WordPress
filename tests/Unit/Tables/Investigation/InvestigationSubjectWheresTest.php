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
}
