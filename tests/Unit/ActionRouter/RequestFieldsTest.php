<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\RequestFields;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

/**
 * Request fields are returned only when they have the expected shape. Nothing is coerced: an array never becomes
 * text, the ID 1, or "yes".
 */
class RequestFieldsTest extends BaseUnitTest {

	public function test_string_returns_text_and_defaults_only_when_missing() :void {
		$fields = new RequestFields( [ 'text' => 'abc', 'empty' => '', 'null' => null ] );

		$this->assertSame( 'abc', $fields->string( 'text' ) );
		$this->assertSame( '', $fields->string( 'empty', 'default' ) );
		$this->assertSame( 'default', $fields->string( 'missing', 'default' ) );
		$this->assertSame( 'default', $fields->string( 'null', 'default' ) );
	}

	public function test_missing_field_without_a_default_is_refused() :void {
		$this->expectException( ActionException::class );
		( new RequestFields( [] ) )->string( 'missing' );
	}

	/**
	 * @param mixed $value
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'notText' )]
	public function test_string_refuses_anything_but_text( $value ) :void {
		$this->expectException( ActionException::class );
		( new RequestFields( [ 'f' => $value ] ) )->string( 'f', '' );
	}

	public static function notText() :array {
		return [
			'list'    => [ [ 'x' ] ],
			'map'     => [ [ 'a' => [ 'b' ] ] ],
			'integer' => [ 7 ],
			'true'    => [ true ],
			'float'   => [ 1.5 ],
		];
	}

	public function test_one_of_accepts_only_listed_text() :void {
		$fields = new RequestFields( [ 'dir' => 'on', 'bad' => 'sideways', 'bool' => true ] );

		$this->assertSame( 'on', $fields->oneOf( 'dir', [ 'on', 'off' ] ) );
		foreach ( [ 'bad', 'bool' ] as $key ) {
			try {
				$fields->oneOf( $key, [ 'on', 'off' ] );
				$this->fail( $key.' should be refused' );
			}
			catch ( ActionException $e ) {
			}
		}
	}

	public function test_positive_int_accepts_integers_and_digit_strings() :void {
		$fields = new RequestFields( [ 'int' => 42, 'digits' => '42', 'padded' => '007' ] );

		$this->assertSame( 42, $fields->positiveInt( 'int' ) );
		$this->assertSame( 42, $fields->positiveInt( 'digits' ) );
		$this->assertSame( 7, $fields->positiveInt( 'padded' ) );
		$this->assertSame( 5, $fields->positiveInt( 'missing', 5 ) );
	}

	/**
	 * @param mixed $value
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'positiveIntCandidates' )]
	public function test_positive_int_parser_contract( $value, ?int $expected ) :void {
		$this->assertSame( $expected, RequestFields::PositiveIntFrom( $value ) );
	}

	public static function positiveIntCandidates() :array {
		return [
			'native int'                => [ 42, 42 ],
			'native max'                => [ \PHP_INT_MAX, \PHP_INT_MAX ],
			'digit string'              => [ '42', 42 ],
			'leading zeroes'            => [ '0042', 42 ],
			'string max'                => [ (string)\PHP_INT_MAX, \PHP_INT_MAX ],
			'zero int'                  => [ 0, null ],
			'zero string'               => [ '0', null ],
			'all zeroes'                => [ '000', null ],
			'negative int'              => [ -1, null ],
			'negative string'           => [ '-1', null ],
			'positive sign'             => [ '+1', null ],
			'whitespace'                => [ ' 42 ', null ],
			'decimal'                   => [ '1.0', null ],
			'exponent'                  => [ '1e2', null ],
			'unicode digit'             => [ "\xD9\xA1", null ],
			'newline suffix'            => [ "42\n", null ],
			'nul suffix'                => [ "42\0", null ],
			'float'                     => [ 1.0, null ],
			'boolean'                   => [ true, null ],
			'empty'                     => [ '', null ],
			'nondigits'                 => [ '1x', null ],
			'overflow'                  => [ (string)\PHP_INT_MAX.'0', null ],
			'array that PHP casts to 1' => [ [ '999' ], null ],
			'nested array'              => [ [ [ 1 ] ], null ],
			'object'                    => [ new \stdClass(), null ],
			'stringable'                => [ new RequestFieldsNumericStringable(), null ],
			'null'                      => [ null, null ],
		];
	}

	public function test_resource_is_not_a_positive_int() :void {
		$resource = \fopen( 'php://memory', 'r' );
		try {
			$this->assertNull( RequestFields::PositiveIntFrom( $resource ) );
		}
		finally {
			\fclose( $resource );
		}
	}

	public function test_positive_int_field_refuses_what_the_parser_rejects() :void {
		$this->expectException( ActionException::class );
		( new RequestFields( [ 'id' => [ '999' ] ] ) )->positiveInt( 'id', 0 );
	}

	public function test_positive_int_list_keeps_only_genuine_ids() :void {
		$fields = new RequestFields( [
			'ok'    => [ 1, '2', '03' ],
			'mixed' => [ 4, [ '999' ], 0, '-1', true, 'x', '5' ],
		] );

		$this->assertSame( [ 1, 2, 3 ], $fields->positiveIntList( 'ok' ) );
		$this->assertSame( [ 4, 5 ], $fields->positiveIntList( 'mixed' ), 'An array entry must never become ID 1.' );
		$this->assertSame( [], $fields->positiveIntList( 'missing', [] ) );
	}

	public function test_positive_int_list_refuses_a_field_that_is_not_a_list() :void {
		$this->expectException( ActionException::class );
		( new RequestFields( [ 'rids' => '5' ] ) )->positiveIntList( 'rids', [] );
	}

	public function test_string_list_keeps_only_text_entries() :void {
		$fields = new RequestFields( [
			'ok'    => [ 'a', 'b' ],
			'mixed' => [ 'a', [ 'b' ], 3, null, true, 'c' ],
			'keyed' => [ 'x' => 'a', 'y' => 'b' ],
		] );

		$this->assertSame( [ 'a', 'b' ], $fields->stringList( 'ok' ) );
		$this->assertSame( [ 'a', 'c' ], $fields->stringList( 'mixed' ), 'Nothing but text is kept, and nothing is turned into text.' );
		$this->assertSame( [ 'a', 'b' ], $fields->stringList( 'keyed' ) );
		$this->assertSame( [], $fields->stringList( 'missing', [] ) );
	}

	public function test_string_list_refuses_a_field_that_is_not_a_list() :void {
		$this->expectException( ActionException::class );
		( new RequestFields( [ 'keys' => 'a,b' ] ) )->stringList( 'keys', [] );
	}

	public function test_array_returns_the_field_as_it_arrived() :void {
		$form = [ 'label' => 'Office', 'nested' => [ 'a' => 1 ] ];
		$fields = new RequestFields( [ 'form' => $form, 'empty' => [] ] );

		$this->assertSame( $form, $fields->array( 'form' ) );
		$this->assertSame( [], $fields->array( 'empty', [ 'default' ] ) );
		$this->assertSame( [ 'default' ], $fields->array( 'missing', [ 'default' ] ) );
	}

	/**
	 * @param mixed $value
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'notAnArray' )]
	public function test_array_refuses_anything_but_an_array( $value ) :void {
		$this->expectException( ActionException::class );
		( new RequestFields( [ 'form' => $value ] ) )->array( 'form', [] );
	}

	public static function notAnArray() :array {
		return [
			'text'    => [ 'a=1&b=2' ],
			'number'  => [ 5 ],
			'boolean' => [ true ],
		];
	}

	public function test_missing_array_without_a_default_is_refused() :void {
		$this->expectException( ActionException::class );
		( new RequestFields( [] ) )->array( 'form' );
	}

	public function test_flag_accepts_only_its_two_tokens() :void {
		$fields = new RequestFields( [
			'yes'       => 'true',
			'no'        => 'false',
			'bool_yes'  => true,
			'bool_no'   => false,
			'one'       => 1,
			'zero'      => '0',
			'other'     => 'yes',
			'list'      => [ 'true' ],
		] );

		$this->assertTrue( $fields->flag( 'yes', 'true', 'false' ) );
		$this->assertFalse( $fields->flag( 'no', 'true', 'false' ) );
		$this->assertTrue( $fields->flag( 'bool_yes', 'true', 'false' ) );
		$this->assertFalse( $fields->flag( 'bool_no', 'true', 'false' ) );
		$this->assertTrue( $fields->flag( 'one', '1', '0' ) );
		$this->assertFalse( $fields->flag( 'zero', '1', '0' ) );
		$this->assertFalse( $fields->flag( 'missing', 'Y', 'N', false ) );

		foreach ( [ 'other', 'list' ] as $key ) {
			try {
				$fields->flag( $key, 'true', 'false' );
				$this->fail( $key.' should be refused' );
			}
			catch ( ActionException $e ) {
			}
		}
	}

	public function test_default_is_returned_as_given_so_it_can_be_a_sentinel() :void {
		$fields = new RequestFields( [ 'rid' => [ '999' ] ] );

		$this->assertSame( -1, $fields->positiveInt( 'missing', -1 ) );
		$this->expectException( ActionException::class );
		$fields->positiveInt( 'rid', -1 );
	}
}

class RequestFieldsNumericStringable {
	public function __toString() :string {
		return '42';
	}
}
