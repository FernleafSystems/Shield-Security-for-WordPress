<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\Worpdrive\Filesystem;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Utility\Base64PayloadDecoder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class Base64PayloadDecoderTest extends BaseUnitTest {

	public function test_optional_list_drops_invalid_and_empty_decoded_values() :void {
		$decoded = ( new Base64PayloadDecoder() )->decodeOptionalList( [
			\base64_encode( 'wp-content/cache' ),
			'not valid base64',
			\base64_encode( '' ),
			\base64_encode( '#\.log$#' ),
		] );

		$this->assertSame( [
			'wp-content/cache',
			'#\.log$#',
		], $decoded );
	}

	public function test_required_list_throws_for_invalid_payload() :void {
		$this->expectException( \InvalidArgumentException::class );

		( new Base64PayloadDecoder() )->decodeRequiredList( [
			\base64_encode( 'wp-content/index.php' ),
			'not valid base64',
		] );
	}

	public function test_required_list_throws_for_empty_decoded_path() :void {
		$this->expectException( \InvalidArgumentException::class );

		( new Base64PayloadDecoder() )->decodeRequiredList( [
			\base64_encode( '' ),
		] );
	}
}
