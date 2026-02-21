<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LoadLogs;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class LoadLogsUserFilterTest extends TestCase {

	public function testForUserIdAppendsReqUidWhereClause() :void {
		$loader = new LoadLogs();
		$loader->forUserId( 14 );

		$this->assertSame(
			[ '`req`.`uid`=14' ],
			$loader->wheres
		);
	}

	public function testForUserIdPreservesExistingWheres() :void {
		$loader = new LoadLogs();
		$loader->wheres = [ "`log`.`event_slug`='foo'" ];
		$loader->forUserId( 9 );

		$this->assertSame(
			[
				"`log`.`event_slug`='foo'",
				'`req`.`uid`=9',
			],
			$loader->wheres
		);
	}

	public function testForUserIdIgnoresNonPositiveIds() :void {
		$loader = new LoadLogs();
		$loader->forUserId( 0 );

		$this->assertNull( $loader->wheres );
	}
}
