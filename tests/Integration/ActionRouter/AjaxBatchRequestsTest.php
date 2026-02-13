<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionProcessor,
	Actions\AjaxBatchRequests,
	Actions\PluginBadgeClose,
	Exceptions\ActionException,
	Exceptions\UserAuthRequiredException
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class AjaxBatchRequestsTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();

		$userId = self::factory()->user->create( [
			'role' => 'administrator',
		] );
		\wp_set_current_user( $userId );
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function invalidBadgeCloseRequest() :array {
		return [
			'ex'      => PluginBadgeClose::SLUG,
			'exnonce' => 'invalid_nonce',
		];
	}

	public function test_batch_requires_authenticated_user() {
		\wp_set_current_user( 0 );

		$this->expectException( UserAuthRequiredException::class );
		$this->processor()->processAction( AjaxBatchRequests::SLUG, [
			'requests' => [],
		] );
	}

	public function test_batch_rejects_request_count_above_limit() {
		$requests = [];
		for ( $i = 0; $i < 21; $i++ ) {
			$requests[] = [
				'id'      => 'item_'.$i,
				'request' => [],
			];
		}

		$this->expectException( ActionException::class );
		$this->processor()->processAction( AjaxBatchRequests::SLUG, [
			'requests' => $requests
		] );
	}

	public function test_batch_returns_nonce_failure_for_invalid_subrequest() {
		$response = $this->processor()->processAction( AjaxBatchRequests::SLUG, [
			'requests' => [
				[
					'id'      => 'bad_nonce',
					'request' => [
						'ex'      => PluginBadgeClose::SLUG,
						'exnonce' => 'invalid_nonce',
					],
				]
			],
		] );

		$payload = $response->payload();
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertArrayHasKey( 'bad_nonce', $payload[ 'results' ] );
		$this->assertFalse( $payload[ 'results' ][ 'bad_nonce' ][ 'success' ] );
		$this->assertEquals( 401, $payload[ 'results' ][ 'bad_nonce' ][ 'status_code' ] );
	}

	public function test_batch_processes_mixed_subrequests_independently() {
		$valid = ActionData::Build( PluginBadgeClose::class );
		$invalid = ActionData::Build( PluginBadgeClose::class );
		$invalid[ 'exnonce' ] = 'invalid_nonce';

		$response = $this->processor()->processAction( AjaxBatchRequests::SLUG, [
			'requests' => [
				[
					'id'      => 'valid',
					'request' => $valid,
				],
				[
					'id'      => 'invalid',
					'request' => $invalid,
				],
			],
		] );

		$payload = $response->payload();
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertArrayHasKey( 'valid', $payload[ 'results' ] );
		$this->assertArrayHasKey( 'invalid', $payload[ 'results' ] );

		$this->assertIsArray( $payload[ 'results' ][ 'valid' ][ 'data' ] );
		$this->assertEquals( 200, $payload[ 'results' ][ 'valid' ][ 'status_code' ] );

		$this->assertFalse( $payload[ 'results' ][ 'invalid' ][ 'success' ] );
		$this->assertEquals( 401, $payload[ 'results' ][ 'invalid' ][ 'status_code' ] );
	}

	public function test_batch_processes_only_last_duplicate_id_occurrence() {
		$invalid = $this->invalidBadgeCloseRequest();

		$response = $this->processor()->processAction( AjaxBatchRequests::SLUG, [
			'requests' => [
				[
					'id'      => 'dup',
					'request' => $invalid,
				],
				[
					'id'      => 'middle',
					'request' => $invalid,
				],
				[
					'id'      => 'dup',
					'request' => $invalid,
				],
			],
		] );

		$payload = $response->payload();
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertCount( 2, $payload[ 'results' ] );
		$this->assertSame( [ 'middle', 'dup' ], \array_keys( $payload[ 'results' ] ) );
	}

	public function test_batch_duplicate_ids_use_trimmed_equivalence_and_last_occurrence_order() {
		$invalid = $this->invalidBadgeCloseRequest();

		$response = $this->processor()->processAction( AjaxBatchRequests::SLUG, [
			'requests' => [
				[
					'id'      => ' dup ',
					'request' => $invalid,
				],
				[
					'id'      => 'middle',
					'request' => $invalid,
				],
				[
					'id'      => 'dup',
					'request' => $invalid,
				],
			],
		] );

		$payload = $response->payload();
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertCount( 2, $payload[ 'results' ] );
		$this->assertSame( [ 'middle', 'dup' ], \array_keys( $payload[ 'results' ] ) );
	}

	public function test_batch_rejects_nested_batch_subrequest() {
		$nested = ActionData::Build( AjaxBatchRequests::class );
		$nested[ 'requests' ] = [];

		$response = $this->processor()->processAction( AjaxBatchRequests::SLUG, [
			'requests' => [
				[
					'id'      => 'nested',
					'request' => $nested,
				],
			],
		] );

		$payload = $response->payload();
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertArrayHasKey( 'nested', $payload[ 'results' ] );
		$this->assertFalse( $payload[ 'results' ][ 'nested' ][ 'success' ] );
		$this->assertEquals( 400, $payload[ 'results' ][ 'nested' ][ 'status_code' ] );
		$this->assertStringContainsString(
			'Nested batch requests',
			$payload[ 'results' ][ 'nested' ][ 'data' ][ 'error' ] ?? ''
		);
	}

	public function test_batch_subresponse_does_not_expose_internal_action_data() {
		$valid = ActionData::Build( PluginBadgeClose::class );

		$response = $this->processor()->processAction( AjaxBatchRequests::SLUG, [
			'requests' => [
				[
					'id'      => 'badge',
					'request' => $valid,
				],
			],
		] );

		$payload = $response->payload();
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertArrayHasKey( 'badge', $payload[ 'results' ] );
		$this->assertIsArray( $payload[ 'results' ][ 'badge' ][ 'data' ] );
		$this->assertArrayNotHasKey( 'action_data', $payload[ 'results' ][ 'badge' ][ 'data' ] );
	}

	public function test_batch_invalid_subrequest_slug_returns_client_error() {
		$response = $this->processor()->processAction( AjaxBatchRequests::SLUG, [
			'requests' => [
				[
					'id'      => 'invalid_slug',
					'request' => [
						'ex'      => 'definitely_not_real_action_slug',
						'exnonce' => 'invalid_nonce',
					],
				],
			],
		] );

		$payload = $response->payload();
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertArrayHasKey( 'invalid_slug', $payload[ 'results' ] );
		$this->assertFalse( $payload[ 'results' ][ 'invalid_slug' ][ 'success' ] );
		$this->assertEquals( 400, $payload[ 'results' ][ 'invalid_slug' ][ 'status_code' ] );
		$this->assertNotEquals( 500, $payload[ 'results' ][ 'invalid_slug' ][ 'status_code' ] );
		$this->assertStringContainsString(
			'no action handler',
			\strtolower( $payload[ 'results' ][ 'invalid_slug' ][ 'data' ][ 'error' ] ?? '' )
		);
	}
}
