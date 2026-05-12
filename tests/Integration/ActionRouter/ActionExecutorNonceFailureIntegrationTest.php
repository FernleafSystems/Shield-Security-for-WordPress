<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionRoutingController,
	Actions\SecurityAdminAuthClear
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
			return static function ( $message ) :void {
				$text = \is_scalar( $message ) ? (string)$message : ( \wp_json_encode( $message ) ?: 'wp_die' );
				throw new ActionExecutorNonceFailureWpDieException( $text );
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
			$message = $e->getMessage();
			$this->assertIsString( $message );
			$this->assertNotSame( '', \trim( $message ) );
			$this->assertStringNotContainsString( 'Action Slug:', $message );
			$this->assertStringNotContainsString( 'Data:', $message );
			$this->assertStringNotContainsString( SecurityAdminAuthClear::SLUG, $message );
			$this->assertStringNotContainsString( 'nonce-leak-marker', $message );
			$this->assertStringNotContainsString( ActionData::FIELD_NONCE, $message );
		}
		finally {
			\remove_filter( 'wp_die_handler', $filter );
			$this->restoreActionNonceContext( $requestBagsSnapshot );
		}
	}
}

class ActionExecutorNonceFailureWpDieException extends \RuntimeException {
}
