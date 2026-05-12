<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Bootstrap;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices\DbPrechecks;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class PrechecksLazyIntegrationTest extends ShieldIntegrationTestCase {

	public function test_prechecks_are_not_computed_until_requested() :void {
		$con = $this->requireController();
		$ref = new \ReflectionClass( $con );
		$prop = $ref->getProperty( 'prechecks' );
		$prop->setAccessible( true );
		$prop->setValue( $con, null );

		$this->assertNull( $prop->getValue( $con ) );

		( new DbPrechecks() )->check();

		$this->assertIsArray( $prop->getValue( $con ) );
		$this->assertArrayHasKey( 'dbs', $prop->getValue( $con ) );
		$this->assertNotEmpty( $prop->getValue( $con )[ 'dbs' ] );
	}
}
