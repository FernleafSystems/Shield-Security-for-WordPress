<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionProcessor,
	Actions\AjaxRender,
	Actions\AjaxBatchRequests,
	Actions\BaseAction,
	Actions\MfaEmailDisable,
	Actions\PluginBadgeClose,
	Actions\PluginImportExport_UpdateNotified,
	Actions\PluginReinstall,
	Exceptions\ActionException,
	Exceptions\UserAuthRequiredException
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class AjaxBatchRequestsTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();

		$this->loginAsAdministrator();
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
		for ( $i = 0; $i < 51; $i++ ) {
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
		$this->assertSame( AjaxBatchRequests::ERROR_INVALID_NONCE, $payload[ 'results' ][ 'bad_nonce' ][ 'error_code' ] ?? '' );
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
		$this->assertArrayHasKey( 'success', $payload[ 'results' ][ 'valid' ][ 'data' ] );
		$this->assertArrayHasKey( 'page_reload', $payload[ 'results' ][ 'valid' ][ 'data' ] );
		$this->assertArrayHasKey( 'message', $payload[ 'results' ][ 'valid' ][ 'data' ] );
		$this->assertArrayHasKey( 'error', $payload[ 'results' ][ 'valid' ][ 'data' ] );
		$this->assertArrayHasKey( 'html', $payload[ 'results' ][ 'valid' ][ 'data' ] );

		$this->assertFalse( $payload[ 'results' ][ 'invalid' ][ 'success' ] );
		$this->assertEquals( 401, $payload[ 'results' ][ 'invalid' ][ 'status_code' ] );
		$this->assertSame( AjaxBatchRequests::ERROR_INVALID_NONCE, $payload[ 'results' ][ 'invalid' ][ 'error_code' ] ?? '' );
		$this->assertArrayHasKey( 'success', $payload[ 'results' ][ 'invalid' ][ 'data' ] );
		$this->assertArrayHasKey( 'page_reload', $payload[ 'results' ][ 'invalid' ][ 'data' ] );
		$this->assertArrayHasKey( 'message', $payload[ 'results' ][ 'invalid' ][ 'data' ] );
		$this->assertArrayHasKey( 'error', $payload[ 'results' ][ 'invalid' ][ 'data' ] );
		$this->assertArrayHasKey( 'html', $payload[ 'results' ][ 'invalid' ][ 'data' ] );
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
		$this->assertSame( AjaxBatchRequests::ERROR_NESTED_BATCH_REQUEST, $payload[ 'results' ][ 'nested' ][ 'error_code' ] ?? '' );
		$this->assertSame( AjaxBatchRequests::ERROR_NESTED_BATCH_REQUEST, $payload[ 'results' ][ 'nested' ][ 'data' ][ 'error_code' ] ?? '' );
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
		$this->assertSame( AjaxBatchRequests::ERROR_ACTION_NOT_FOUND, $payload[ 'results' ][ 'invalid_slug' ][ 'error_code' ] ?? '' );
		$this->assertSame( AjaxBatchRequests::ERROR_ACTION_NOT_FOUND, $payload[ 'results' ][ 'invalid_slug' ][ 'data' ][ 'error_code' ] ?? '' );
	}

	public function test_batch_ajax_render_subrequest_rejects_non_render_target() :void {
		$subrequest = ActionData::Build( AjaxRender::class, true, [
			'render_slug' => PluginImportExport_UpdateNotified::SLUG,
		] );

		$response = $this->processor()->processAction( AjaxBatchRequests::SLUG, [
			'requests' => [
				[
					'id'      => 'invalid_render_target',
					'request' => $subrequest,
				],
			],
		] );

		$payload = $response->payload();
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertArrayHasKey( 'invalid_render_target', $payload[ 'results' ] );
		$this->assertFalse( $payload[ 'results' ][ 'invalid_render_target' ][ 'success' ] );
		$this->assertSame( 400, $payload[ 'results' ][ 'invalid_render_target' ][ 'status_code' ] );
		$this->assertSame( AjaxBatchRequests::ERROR_ACTION_EXCEPTION, $payload[ 'results' ][ 'invalid_render_target' ][ 'error_code' ] ?? '' );
		$this->assertSame( AjaxBatchRequests::ERROR_ACTION_EXCEPTION, $payload[ 'results' ][ 'invalid_render_target' ][ 'data' ][ 'error_code' ] ?? '' );
	}

	public function test_batch_malformed_item_returns_action_exception_error_code() :void {
		$response = $this->processor()->processAction( AjaxBatchRequests::SLUG, [
			'requests' => [
				[ 'id' => 'missing_request' ],
				'not_an_array',
			],
		] );

		$payload = $response->payload();
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertBatchFailure(
			$payload[ 'results' ][ 'missing_request' ] ?? [],
			400,
			AjaxBatchRequests::ERROR_ACTION_EXCEPTION
		);
		$this->assertBatchFailure(
			$payload[ 'results' ][ 'item_1' ] ?? [],
			400,
			AjaxBatchRequests::ERROR_ACTION_EXCEPTION
		);
	}

	public function test_batch_subrequest_reports_security_admin_requirement_as_machine_code() :void {
		$response = $this->processor()->processAction( AjaxBatchRequests::SLUG, [
			'requests' => [
				[
					'id'      => 'security_admin',
					'request' => ActionData::Build( MfaEmailDisable::class ),
				],
			],
		] );

		$this->assertBatchFailure(
			$response->payload()[ 'results' ][ 'security_admin' ] ?? [],
			401,
			AjaxBatchRequests::ERROR_SECURITY_ADMIN_REQUIRED
		);
	}

	public function test_batch_subrequest_reports_user_auth_requirement_as_machine_code() :void {
		$subscriber = self::factory()->user->create( [
			'role' => 'subscriber',
		] );
		\wp_set_current_user( $subscriber );

		$response = $this->processor()->processAction( AjaxBatchRequests::SLUG, [
			'requests' => [
				[
					'id'      => 'auth_required',
					'request' => ActionData::Build( PluginReinstall::class, true, [
						'file' => 'plugin/plugin.php',
					] ),
				],
			],
		] );

		$this->assertBatchFailure(
			$response->payload()[ 'results' ][ 'auth_required' ] ?? [],
			403,
			AjaxBatchRequests::ERROR_USER_AUTH_REQUIRED
		);
	}

	public function test_batch_subrequest_reports_unexpected_throwable_as_machine_code() :void {
		$subrequest = ActionData::Build( AjaxBatchUnexpectedThrowableAction::class );
		$subrequest[ ActionData::FIELD_EXECUTE ] = AjaxBatchUnexpectedThrowableAction::class;

		$response = $this->processor()->processAction( AjaxBatchRequests::SLUG, [
			'requests' => [
				[
					'id'      => 'unexpected',
					'request' => $subrequest,
				],
			],
		] );

		$this->assertBatchFailure(
			$response->payload()[ 'results' ][ 'unexpected' ] ?? [],
			500,
			AjaxBatchRequests::ERROR_UNEXPECTED
		);
	}

	private function assertBatchFailure( array $result, int $statusCode, string $errorCode ) :void {
		$this->assertFalse( (bool)( $result[ 'success' ] ?? true ) );
		$this->assertSame( $statusCode, (int)( $result[ 'status_code' ] ?? 0 ) );
		$this->assertSame( $errorCode, (string)( $result[ 'error_code' ] ?? '' ) );
		$this->assertSame( $errorCode, (string)( $result[ 'data' ][ 'error_code' ] ?? '' ) );
		$this->assertFalse( (bool)( $result[ 'data' ][ 'success' ] ?? true ) );
	}
}

class AjaxBatchUnexpectedThrowableAction extends BaseAction {

	public const SLUG = 'ajax_batch_unexpected_throwable_test_action';

	protected function getMinimumUserAuthCapability() :string {
		return '';
	}

	protected function isNonceVerifyRequired() :bool {
		return false;
	}

	protected function exec() {
		throw new \RuntimeException( 'unexpected batch throwable' );
	}
}
