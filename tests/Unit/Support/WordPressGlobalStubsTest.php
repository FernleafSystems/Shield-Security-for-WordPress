<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class WordPressGlobalStubsTest extends TestCase {

	public function test_wp_error_supports_keyed_messages_and_data() :void {
		$error = new \WP_Error( 'first_code', 'First message.', [ 'status' => 400 ] );
		$error->add( 'second_code', 'Second message.', [ 'status' => 403 ] );
		$error->add_data( [ 'status' => 409 ], 'second_code' );

		$this->assertSame( 'first_code', $error->get_error_code() );
		$this->assertSame( [ 'first_code', 'second_code' ], $error->get_error_codes() );
		$this->assertSame( 'First message.', $error->get_error_message() );
		$this->assertSame( 'Second message.', $error->get_error_message( 'second_code' ) );
		$this->assertSame( [ 'First message.', 'Second message.' ], $error->get_error_messages() );
		$this->assertSame( [ 'status' => 400 ], $error->get_error_data() );
		$this->assertSame( [ 'status' => 409 ], $error->get_error_data( 'second_code' ) );
		$this->assertSame(
			[
				[ 'status' => 403 ],
				[ 'status' => 409 ],
			],
			$error->get_all_error_data( 'second_code' )
		);
	}

	public function test_wp_error_exports_and_removes_keyed_errors() :void {
		$source = new \WP_Error( 'first_code', 'First message.', [ 'status' => 400 ] );
		$target = new \WP_Error();

		$source->export_to( $target );
		$target->remove( 'first_code' );

		$this->assertSame( 'First message.', $source->get_error_message( 'first_code' ) );
		$this->assertSame( [ 'status' => 400 ], $source->get_error_data( 'first_code' ) );
		$this->assertFalse( $target->has_errors() );
		$this->assertNull( $target->get_error_data( 'first_code' ) );
	}
}
