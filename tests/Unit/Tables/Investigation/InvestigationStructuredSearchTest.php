<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation\InvestigationStructuredSearch;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class InvestigationStructuredSearchTest extends BaseUnitTest {

	public function testHasActiveFiltersWhenSearchContainsRemainingOrStructuredTerms() :void {
		$helper = new InvestigationStructuredSearch();

		$this->assertFalse( $helper->hasActiveFilters( [
			'remaining'  => '',
			'ip'         => '',
			'user_id'    => '',
			'user_name'  => '',
			'user_email' => '',
		] ) );
		$this->assertTrue( $helper->hasActiveFilters( [ 'remaining' => 'abc' ] ) );
		$this->assertTrue( $helper->hasActiveFilters( [ 'ip' => '1.1.1.1' ] ) );
	}

	public function testPassesUserSubjectWithMatchingStructuredUserId() :void {
		$helper = new InvestigationStructuredSearch();

		$this->assertTrue( $helper->passesUserSubject(
			[ 'user_id' => '42', 'user_name' => '', 'user_email' => '' ],
			42,
			fn( string $username ) :int => 0,
			fn( string $email ) :int => 0
		) );
	}

	public function testPassesUserSubjectFailsForDifferentStructuredUser() :void {
		$helper = new InvestigationStructuredSearch();

		$this->assertFalse( $helper->passesUserSubject(
			[ 'user_id' => '99', 'user_name' => '', 'user_email' => '' ],
			42,
			fn( string $username ) :int => 0,
			fn( string $email ) :int => 0
		) );
	}

	public function testFilterRecordsForIpTokenMatchesExpectedRows() :void {
		$helper = new InvestigationStructuredSearch();
		$rows = $helper->filterRecordsForIpToken(
			[
				[ 'rid' => 'a', 'ip' => '1.1.1.1' ],
				[ 'rid' => 'b', 'ip' => '2.2.2.2' ],
				[ 'rid' => 'c', 'ip' => '3.3.3.3' ],
			],
			[ 'ip' => '2.2.2.2' ]
		);

		$this->assertCount( 1, $rows );
		$this->assertSame( 'b', $rows[ 0 ][ 'rid' ] );
	}
}
