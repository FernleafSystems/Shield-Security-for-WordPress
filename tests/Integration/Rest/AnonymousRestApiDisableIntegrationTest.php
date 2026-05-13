<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rest;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components\AnonRestApiDisable;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Core\Rest;

class AnonymousRestApiDisableIntegrationTest extends ShieldIntegrationTestCase {

	private array $hookSnapshots = [];

	private array $optionSnapshot = [];

	private array $servicesSnapshot = [];

	public function set_up() {
		parent::set_up();

		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'disable_anonymous_restapi',
			'api_namespace_exclusions',
		] );
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->snapshotHook( 'init' );
		$this->snapshotHook( 'rest_authentication_errors' );

		\wp_set_current_user( 0 );
		$this->setRestOptions();
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionSnapshot );
		ServicesState::restore( $this->servicesSnapshot );
		$this->restoreHooks();
		\wp_set_current_user( 0 );

		parent::tear_down();
	}

	private function snapshotHook( string $hook ) :void {
		if ( \array_key_exists( $hook, $this->hookSnapshots ) ) {
			return;
		}

		$this->hookSnapshots[ $hook ] = \array_key_exists( $hook, $GLOBALS[ 'wp_filter' ] ?? [] )
			? $this->cloneFilterSnapshot( $GLOBALS[ 'wp_filter' ][ $hook ] )
			: null;
	}

	private function cloneFilterSnapshot( $filter ) {
		return \is_object( $filter ) ? clone $filter : $filter;
	}

	private function clearHook( string $hook ) :void {
		$this->snapshotHook( $hook );
		unset( $GLOBALS[ 'wp_filter' ][ $hook ] );
	}

	private function restoreHooks() :void {
		foreach ( $this->hookSnapshots as $hook => $snapshot ) {
			if ( $snapshot === null ) {
				unset( $GLOBALS[ 'wp_filter' ][ $hook ] );
			}
			else {
				$GLOBALS[ 'wp_filter' ][ $hook ] = $snapshot;
			}
		}
	}

	private function setRestOptions( array $overrides = [] ) :void {
		$options = \array_merge( [
			'disable_anonymous_restapi' => 'Y',
			'api_namespace_exclusions'  => [ 'shield' ],
		], $overrides );

		$opts = $this->requireController()->opts;
		foreach ( $options as $key => $value ) {
			$opts->optSet( $key, $value );
		}
	}

	private function setRestNamespace( ?string $namespace ) :void {
		ServicesState::mergeItems( [
			'service_rest' => new class( $namespace ) extends Rest {
				private ?string $namespace;

				public function __construct( ?string $namespace ) {
					$this->namespace = $namespace;
				}

				public function getNamespace() :?string {
					return $this->namespace;
				}
			},
		] );
	}

	public function test_anonymous_non_excluded_namespace_returns_wp_error_and_fires_event() :void {
		$this->setRestNamespace( 'wp' );
		$this->captureShieldEvents();

		$result = ( new AnonRestApiDisable() )->disableAnonymousRestApi( null );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'shield_block_anon_restapi', $result->get_error_code() );
		$this->assertSame(
			rest_authorization_required_code(),
			(int)( $result->get_error_data()[ 'status' ] ?? 0 )
		);

		$events = $this->getCapturedEventsByKey( 'block_anonymous_restapi' );
		$this->assertCount( 1, $events );
		$this->assertSame( 'wp', $events[ 0 ][ 'meta' ][ 'audit_params' ][ 'namespace' ] ?? '' );
	}

	public function test_authenticated_status_passes_through_without_event() :void {
		$this->setRestNamespace( 'wp' );
		$this->captureShieldEvents();

		$this->assertTrue( ( new AnonRestApiDisable() )->disableAnonymousRestApi( true ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'block_anonymous_restapi' ) );
	}

	public function test_existing_wp_error_passes_through_without_event() :void {
		$this->setRestNamespace( 'wp' );
		$existing = new \WP_Error( 'existing_rest_error', 'Existing REST error', [ 'status' => 418 ] );
		$this->captureShieldEvents();

		$result = ( new AnonRestApiDisable() )->disableAnonymousRestApi( $existing );

		$this->assertSame( $existing, $result );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'block_anonymous_restapi' ) );
	}

	public function test_excluded_namespace_passes_through_without_event() :void {
		$this->setRestOptions( [
			'api_namespace_exclusions' => [ 'wp' ],
		] );
		$this->setRestNamespace( 'wp' );
		$this->captureShieldEvents();

		$this->assertNull( ( new AnonRestApiDisable() )->disableAnonymousRestApi( null ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'block_anonymous_restapi' ) );
	}

	public function test_empty_namespace_passes_through_without_event() :void {
		$this->setRestNamespace( '' );
		$this->captureShieldEvents();

		$this->assertNull( ( new AnonRestApiDisable() )->disableAnonymousRestApi( null ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'block_anonymous_restapi' ) );
	}

	public function test_execute_registers_rest_authentication_filter_for_anonymous_requests_when_enabled() :void {
		$this->clearHook( 'init' );
		$this->clearHook( 'rest_authentication_errors' );
		$this->setRestOptions( [
			'disable_anonymous_restapi' => 'Y',
		] );
		\wp_set_current_user( 0 );

		$component = new AnonRestApiDisable();
		$component->execute();
		\do_action( 'init' );

		$this->assertSame( 99, \has_filter( 'rest_authentication_errors', [ $component, 'disableAnonymousRestApi' ] ) );
	}

	public function test_execute_does_not_register_rest_filter_for_logged_in_requests() :void {
		$this->clearHook( 'init' );
		$this->clearHook( 'rest_authentication_errors' );
		$this->setRestOptions( [
			'disable_anonymous_restapi' => 'Y',
		] );
		$userId = self::factory()->user->create( [
			'role' => 'administrator',
		] );
		\wp_set_current_user( $userId );

		( new AnonRestApiDisable() )->execute();
		\do_action( 'init' );

		$this->assertFalse( \has_filter( 'rest_authentication_errors' ) );
	}

	public function test_execute_does_not_register_rest_filter_when_option_is_disabled() :void {
		$this->clearHook( 'init' );
		$this->clearHook( 'rest_authentication_errors' );
		$this->setRestOptions( [
			'disable_anonymous_restapi' => 'N',
		] );
		\wp_set_current_user( 0 );

		( new AnonRestApiDisable() )->execute();
		\do_action( 'init' );

		$this->assertFalse( \has_filter( 'rest_authentication_errors' ) );
	}
}
