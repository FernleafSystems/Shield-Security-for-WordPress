<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\LoadRequestLogs;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class LoadRequestLogsUserFilterTest extends TestCase {

	public function testForUserIdAppendsReqUidWhereClause() :void {
		$loader = new LoadRequestLogs();
		$loader->forUserId( 14 );

		$this->assertSame(
			[ '`req`.`uid`=14' ],
			$loader->wheres
		);
	}

	public function testForUserIdPreservesExistingWheres() :void {
		$loader = new LoadRequestLogs();
		$loader->wheres = [ "`req`.`type`='H'" ];
		$loader->forUserId( 9 );

		$this->assertSame(
			[
				"`req`.`type`='H'",
				'`req`.`uid`=9',
			],
			$loader->wheres
		);
	}

	public function testForUserIdIgnoresNonPositiveIds() :void {
		$loader = new LoadRequestLogs();
		$loader->forUserId( 0 );

		$this->assertNull( $loader->wheres );
	}
}
