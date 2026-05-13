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
			'html'    => 'html_payload',
		] );

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertFalse( $payload[ 'page_reload' ] ?? true );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertSame( '', $payload[ 'error' ] ?? null );
		$this->assertSame( 'html_payload', $payload[ 'html' ] ?? '' );
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
		$this->assertArrayHasKey( 'message', $payload );
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
				'message'    => 'payload_message',
				'error'      => 'payload_error',
				'page_title' => 'payload_page',
				'show_toast' => false,
			],
			'fallback_message',
			'fallback_error'
		);

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertSame( 'payload_message', $payload[ 'message' ] ?? '' );
		$this->assertSame( 'payload_error', $payload[ 'error' ] ?? '' );
		$this->assertSame( 'payload_page', $payload[ 'page_title' ] ?? '' );
		$this->assertFalse( $payload[ 'show_toast' ] ?? true );
	}

	public function test_for_ajax_auth_refresh_builds_reload_contract() :void {
		$payload = ResponseEnvelopeNormalizer::forAjaxAuthRefresh();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertTrue( $payload[ 'auth_refresh_required' ] ?? false );
		$this->assertFalse( $payload[ 'show_toast' ] ?? true );
		$this->assertSame( 'user_auth_required', $payload[ 'error_code' ] ?? '' );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertSame( $payload[ 'message' ] ?? '', $payload[ 'error' ] ?? '' );
		$this->assertSame( '', $payload[ 'html' ] ?? null );
	}
}
