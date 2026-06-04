<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Host;

use FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\Host\ShieldWorpdriveDatabase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Fixtures\WorpdriveTestDb;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\WorpdriveUnitTestCase;

class ShieldWorpdriveDatabaseTest extends WorpdriveUnitTestCase {

	public function test_database_adapter_delegates_to_shield_database_service() :void {
		$db = new WorpdriveTestDb();
		ServicesState::mergeItems( [
			'service_wpdb' => $db,
		] );
		$adapter = new ShieldWorpdriveDatabase();

		$this->assertSame( 'wp_', $adapter->getPrefix() );
		$this->assertSame( 'wp_site_', $adapter->getPrefix( false ) );
		$this->assertInstanceOf( \wpdb::class, $adapter->loadWpdb() );
		$this->assertSame( [ [ 'query' => 'SELECT default' ] ], $adapter->selectCustom( 'SELECT default' ) );
		$this->assertSame( [ [ 'query' => 'SELECT 1' ] ], $adapter->selectCustom( 'SELECT 1', \ARRAY_A ) );
		$this->assertTrue( $adapter->doSql( 'UPDATE test SET a=1' ) );
		$this->assertSame( 'value', $adapter->getVar( 'SELECT value' ) );
		$this->assertSame( [ [ 'Name' => 'wp_posts' ] ], $adapter->showTableStatus() );
		$this->assertSame( [ [ 'Name' => 'wp_posts' ] ], $adapter->showTableStatus( \ARRAY_A ) );
		$this->assertSame(
			[
				[ 'getPrefix', true ],
				[ 'getPrefix', false ],
				[ 'loadWpdb' ],
				[ 'selectCustom', 'SELECT default', \ARRAY_A ],
				[ 'selectCustom', 'SELECT 1', \ARRAY_A ],
				[ 'doSql', 'UPDATE test SET a=1' ],
				[ 'getVar', 'SELECT value' ],
				[ 'showTableStatus', \OBJECT ],
				[ 'showTableStatus', \ARRAY_A ],
			],
			$db->calls
		);
	}
}
