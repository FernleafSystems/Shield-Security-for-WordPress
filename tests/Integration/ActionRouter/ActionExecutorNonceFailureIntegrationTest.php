<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionExecutor,
	ActionRoutingController,
	Actions\SecurityAdminAuthClear,
	Exceptions\InvalidActionNonceException
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\ActionRequestNonceFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ActionExecutorNonceFailureIntegrationTest extends ShieldIntegrationTestCase {

	use ActionRequestNonceFixture;

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->loginAsAdministrator();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
	}

	public function tear_down() {
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_non_ajax_invalid_nonce_does_not_leak_slug_or_request_data() :void {
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI'    => '/wp-admin/admin.php?page=shield',
			],
			[],
			[],
			[
				'path'       => '/wp-admin/admin.php',
				'wp_is_ajax' => false,
			]
		);
		$requestBagsSnapshot = $this->seedActionNonceContext( SecurityAdminAuthClear::class );
		$this->mergeCurrentRequestTransport( [
			ActionData::FIELD_NONCE => 'invalid_nonce',
		] );

		$filter = static function () {
			return static function ( $message, $title = '', $args = [] ) :void {
				throw new ActionExecutorNonceFailureWpDieException(
					\is_array( $args ) ? $args : []
				);
			};
		};
		\add_filter( 'wp_die_handler', $filter );

		try {
			$this->requireController()->action_router->action(
				SecurityAdminAuthClear::SLUG,
				[
					'leak_marker' => 'nonce-leak-marker',
				],
				ActionRoutingController::ACTION_SHIELD
			);
			$this->fail( 'Expected wp_die() for invalid non-AJAX nonce.' );
		}
		catch ( ActionExecutorNonceFailureWpDieException $e ) {
			$args = $e->args();
			$this->assertSame( ActionExecutor::WP_DIE_INVALID_NONCE_CODE, $args[ 'code' ] ?? '' );
			$this->assertSame( ActionExecutor::WP_DIE_INVALID_NONCE_STATUS, $args[ 'response' ] ?? 0 );
			$this->assertArrayNotHasKey( 'action_data', $args );
			$this->assertArrayNotHasKey( ActionData::FIELD_NONCE, $args );
		}
		finally {
			\remove_filter( 'wp_die_handler', $filter );
			$this->restoreActionNonceContext( $requestBagsSnapshot );
		}
	}

	public function test_ajax_invalid_nonce_still_throws_nonce_exception() :void {
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI'    => '/wp-admin/admin-ajax.php',
			],
			[],
			[],
			[
				'path'       => '/wp-admin/admin-ajax.php',
				'wp_is_ajax' => true,
			]
		);
		$requestBagsSnapshot = $this->seedActionNonceContext( SecurityAdminAuthClear::class );
		$this->mergeCurrentRequestTransport( [
			ActionData::FIELD_NONCE => 'invalid_nonce',
		] );
		\add_filter( 'wp_doing_ajax', '__return_true', 1000 );

		try {
			$this->expectException( InvalidActionNonceException::class );
			$this->requireController()->action_router->action(
				SecurityAdminAuthClear::SLUG,
				[
					'leak_marker' => 'nonce-leak-marker',
				],
				ActionRoutingController::ACTION_AJAX
			);
		}
		finally {
			\remove_filter( 'wp_doing_ajax', '__return_true', 1000 );
			$this->restoreActionNonceContext( $requestBagsSnapshot );
		}
	}
}

class ActionExecutorNonceFailureWpDieException extends \RuntimeException {

	private array $args;

	public function __construct( array $args ) {
		parent::__construct();
		$this->args = $args;
	}

	public function args() :array {
		return $this->args;
	}
}
