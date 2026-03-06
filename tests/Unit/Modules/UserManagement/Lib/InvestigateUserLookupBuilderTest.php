<?php declare( strict_types=1 );

namespace {
	if ( !\class_exists( '\WP_User' ) ) {
		class WP_User {
			public int $ID = 0;
			public string $user_login = '';
			public string $user_email = '';
			public array $roles = [];
		}
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\UserManagement\Lib {

	use Brain\Monkey\Functions;
	use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\InvestigateUserLookupBuilder;
	use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

	class InvestigateUserLookupBuilderTest extends BaseUnitTest {

		protected function setUp() :void {
			parent::setUp();
			Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		}

		public function test_format_label_includes_id_primary_role_login_and_email() :void {
			$user = new \WP_User();
			$user->ID = 42;
			$user->user_login = 'operator';
			$user->user_email = 'operator@example.com';
			$user->roles = [ 'administrator', 'editor' ];

			$label = ( new InvestigateUserLookupBuilder() )->formatLabel( $user );

			$this->assertSame( '[ID:42 | Administrator] operator | operator@example.com', $label );
		}
	}
}
