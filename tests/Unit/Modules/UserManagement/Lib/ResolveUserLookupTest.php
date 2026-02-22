<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\UserManagement\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\ResolveUserLookup;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class ResolveUserLookupTest extends TestCase {

	public function test_empty_lookup_short_circuits_without_queries() :void {
		$resolver = new ResolveUserLookupTestStub();

		$this->assertNull( $resolver->resolve( '   ' ) );
		$this->assertSame( [], $resolver->calls );
	}

	public function test_numeric_lookup_uses_user_id_resolution() :void {
		$resolver = new ResolveUserLookupTestStub();
		$resolver->resolve( '42' );

		$this->assertSame( [ 'id:42' ], $resolver->calls );
	}

	public function test_email_lookup_uses_email_resolution() :void {
		$resolver = new ResolveUserLookupTestStub();
		$resolver->resolve( 'test@example.com' );

		$this->assertSame( [ 'email:test@example.com' ], $resolver->calls );
	}

	public function test_username_lookup_uses_username_resolution() :void {
		$resolver = new ResolveUserLookupTestStub();
		$resolver->resolve( 'alice_admin' );

		$this->assertSame( [ 'username:alice_admin' ], $resolver->calls );
	}
}

class ResolveUserLookupTestStub extends ResolveUserLookup {

	public array $calls = [];

	protected function normalizeLookup( string $lookup ) :string {
		return \trim( $lookup );
	}

	protected function isValidEmail( string $lookup ) :bool {
		return \str_contains( $lookup, '@' );
	}

	protected function getUserById( int $id ) :?\WP_User {
		$this->calls[] = 'id:'.$id;
		return null;
	}

	protected function getUserByEmail( string $email ) :?\WP_User {
		$this->calls[] = 'email:'.$email;
		return null;
	}

	protected function getUserByUsername( string $username ) :?\WP_User {
		$this->calls[] = 'username:'.$username;
		return null;
	}
}
