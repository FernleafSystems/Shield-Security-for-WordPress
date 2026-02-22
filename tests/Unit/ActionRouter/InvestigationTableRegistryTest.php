<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableRegistry;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class InvestigationTableRegistryTest extends BaseUnitTest {

	public function testRegistryContainsExpectedTableTypes() :void {
		$map = InvestigationTableRegistry::tableMap();
		$this->assertArrayHasKey( 'activity', $map );
		$this->assertArrayHasKey( 'traffic', $map );
		$this->assertArrayHasKey( 'sessions', $map );
		$this->assertArrayHasKey( 'file_scan_results', $map );
	}

	public function testSessionsAllowsOnlyUserSubject() :void {
		$this->assertSame(
			[ 'user' ],
			InvestigationTableRegistry::getAllowedSubjectTypes( 'sessions' )
		);
	}

	public function testBuilderClassesAreRegisteredAsExistingClasses() :void {
		foreach ( \array_keys( InvestigationTableRegistry::tableMap() ) as $tableType ) {
			$builderClass = InvestigationTableRegistry::getBuilderClass( $tableType );
			$this->assertNotSame( '', $builderClass );
			$this->assertTrue( \class_exists( $builderClass ) );
		}
	}
}
