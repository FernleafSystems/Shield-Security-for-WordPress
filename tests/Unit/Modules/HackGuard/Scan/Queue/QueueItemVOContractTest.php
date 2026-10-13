<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\QueueItemVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class QueueItemVOContractTest extends BaseUnitTest {

	/**
	 * @dataProvider provideSerializedItemPayloads
	 */
	public function test_serialized_item_payloads_publish_canonical_lists( $payload, array $expected ) :void {
		$item = ( new QueueItemVO() )->applyFromArray( [
			'items' => $payload,
		] );

		$this->assertSame( $expected, $item->items );
	}

	public static function provideSerializedItemPayloads() :array {
		return [
			'valid list'             => [ self::encode( [ 'first', 'second', 'first' ] ), [ 'first', 'second', 'first' ] ],
			'mixed members'          => [ self::encode( [ 12, 'valid', false, null, '', [], '  ' ] ), [ 'valid', '  ' ] ],
			'empty list'             => [ self::encode( [] ), [] ],
			'json object'            => [ self::encode( [ 'first' => 'valid' ] ), [] ],
			'sparse object'          => [ self::encode( [ 0 => 'first', 2 => 'third' ] ), [] ],
			'numeric-key json object' => [ \base64_encode( '{"0":"first","1":"second"}' ), [] ],
			'json null'              => [ self::encode( null ), [] ],
			'json string'            => [ self::encode( 'valid' ), [] ],
			'json integer'           => [ self::encode( 12 ), [] ],
			'json boolean'           => [ self::encode( true ), [] ],
			'invalid json'           => [ \base64_encode( '{' ), [] ],
			'invalid base64'         => [ '***', [] ],
			'empty encoded payload'  => [ '', [] ],
			'non-string raw payload' => [ 12, [] ],
		];
	}

	public function test_direct_setter_filters_members_and_preserves_order_duplicates_and_reindexing() :void {
		$resource = \fopen( 'php://memory', 'rb' );
		$item = new QueueItemVO();

		try {
			$item->items = [ new \stdClass(), 'first', 12, 'second', '', $resource, 'first' ];
		}
		finally {
			\is_resource( $resource ) && \fclose( $resource );
		}

		$this->assertSame( [ 'first', 'second', 'first' ], $item->items );
	}

	public function test_direct_associative_and_sparse_arrays_are_rejected() :void {
		$item = new QueueItemVO();

		$item->items = [ 'first' => 'valid' ];
		$this->assertSame( [], $item->items );

		$item->items = [ 0 => 'first', 2 => 'third' ];
		$this->assertSame( [], $item->items );
	}

	public function test_meta_retains_existing_outer_array_decode_contract() :void {
		$item = ( new QueueItemVO() )->applyFromArray( [
			'meta' => $this->encode( [
				'associative' => [ 'nested' => true ],
				'scalar'      => 12,
			] ),
		] );

		$this->assertSame( [
			'associative' => [ 'nested' => true ],
			'scalar'      => 12,
		], $item->meta );
	}

	private static function encode( $value ) :string {
		return \base64_encode( (string)\json_encode( $value ) );
	}
}
