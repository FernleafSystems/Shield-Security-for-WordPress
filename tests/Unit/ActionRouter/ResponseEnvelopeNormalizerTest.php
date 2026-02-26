<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ResponseEnvelopeNormalizer;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ResponseEnvelopeNormalizerTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) => $text );
	}

	public function test_for_ajax_applies_expected_defaults() :void {
		$payload = ResponseEnvelopeNormalizer::forAjax( [
			'success' => true,
			'html'    => '<div>ok</div>',
		] );

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertFalse( $payload[ 'page_reload' ] ?? true );
		$this->assertSame( 'No AJAX message provided', $payload[ 'message' ] ?? '' );
		$this->assertSame( '', $payload[ 'error' ] ?? null );
		$this->assertSame( '<div>ok</div>', $payload[ 'html' ] ?? '' );
		$this->assertArrayNotHasKey( 'page_title', $payload );
	}

	public function test_for_rest_process_applies_expected_defaults() :void {
		$payload = ResponseEnvelopeNormalizer::forRestProcess( [
			'success' => true,
		] );

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertFalse( $payload[ 'page_reload' ] ?? true );
		$this->assertSame( '', $payload[ 'message' ] ?? 'x' );
		$this->assertSame( '', $payload[ 'html' ] ?? 'x' );
	}

	public function test_for_batch_subresponse_applies_ajax_defaults() :void {
		$payload = ResponseEnvelopeNormalizer::forBatchSubresponse( [] );

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertFalse( $payload[ 'page_reload' ] ?? true );
		$this->assertSame( 'No AJAX message provided', $payload[ 'message' ] ?? '' );
		$this->assertSame( '', $payload[ 'error' ] ?? null );
		$this->assertSame( '', $payload[ 'html' ] ?? null );
	}

	public function test_for_ajax_adapter_applies_expected_defaults() :void {
		$payload = ResponseEnvelopeNormalizer::forAjaxAdapter( [] );

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertSame( '', $payload[ 'message' ] ?? 'x' );
		$this->assertSame( '', $payload[ 'error' ] ?? 'x' );
		$this->assertSame( '-', $payload[ 'html' ] ?? '' );
		$this->assertSame( '-', $payload[ 'page_title' ] ?? '' );
		$this->assertSame( '-', $payload[ 'page_url' ] ?? '' );
		$this->assertTrue( $payload[ 'show_toast' ] ?? false );
		$this->assertArrayNotHasKey( 'page_reload', $payload );
	}

	public function test_for_ajax_adapter_allows_payload_overrides() :void {
		$payload = ResponseEnvelopeNormalizer::forAjaxAdapter(
			[
				'success'    => true,
				'message'    => 'payload message',
				'error'      => 'payload error',
				'page_title' => 'Payload Page',
				'show_toast' => false,
			],
			'fallback message',
			'fallback error'
		);

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertSame( 'payload message', $payload[ 'message' ] ?? '' );
		$this->assertSame( 'payload error', $payload[ 'error' ] ?? '' );
		$this->assertSame( 'Payload Page', $payload[ 'page_title' ] ?? '' );
		$this->assertFalse( $payload[ 'show_toast' ] ?? true );
	}
}
