<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\UserManagement\Lib\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\FindSessions;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class FindSessionsByUserTest extends TestCase {

	public function testByUserBuildsExpectedUserWhereClause() :void {
		$finder = new class extends FindSessions {
			public array $capturedWheres = [];

			public function lookupFromUserMeta( array $wheres = [], int $limit = 10, string $orderBy = '`user_meta`.`last_login_at`' ) :array {
				$this->capturedWheres = $wheres;
				return [];
			}
		};

		$finder->byUser( 21 );

		$this->assertSame(
			[ '`user_meta`.`user_id`=21' ],
			$finder->capturedWheres
		);
	}

	public function testByUserSkipsLookupForInvalidUserId() :void {
		$finder = new class extends FindSessions {
			public bool $lookupCalled = false;

			public function lookupFromUserMeta( array $wheres = [], int $limit = 10, string $orderBy = '`user_meta`.`last_login_at`' ) :array {
				$this->lookupCalled = true;
				return [];
			}
		};

		$finder->byUser( 0 );

		$this->assertFalse( $finder->lookupCalled );
	}
}
