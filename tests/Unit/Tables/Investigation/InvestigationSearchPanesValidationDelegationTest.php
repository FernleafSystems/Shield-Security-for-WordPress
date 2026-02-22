<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation\{
	BuildActivityLogData,
	BuildTrafficData
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class InvestigationSearchPanesValidationDelegationTest extends BaseUnitTest {

	public function testTrafficValidationFiltersUnexpectedTypeValues() :void {
		$builder = new BuildTrafficData();
		$validated = $builder->exportValidateSearchPanes( [
			'type' => [ 'A', "' OR 1=1 --" ],
		] );

		$this->assertArrayHasKey( 'type', $validated );
		$this->assertSame( [ 'A' ], \array_values( $validated[ 'type' ] ) );
	}

	public function testActivityValidationNormalizesAndFiltersUidValues() :void {
		$builder = new BuildActivityLogData();
		$validated = $builder->exportValidateSearchPanes( [
			'uid' => [ '42', '0', 'not-a-number' ],
		] );

		$this->assertArrayHasKey( 'uid', $validated );
		$this->assertSame( [ 42 ], \array_values( $validated[ 'uid' ] ) );
	}
}
