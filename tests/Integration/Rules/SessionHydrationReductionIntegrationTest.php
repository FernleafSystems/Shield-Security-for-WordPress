<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\IsLoggedInNormal;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Core\Request as ServicesRequest;
use FernleafSystems\Wordpress\Services\Services;

class SessionHydrationReductionIntegrationTest extends ShieldIntegrationTestCase {

	public function test_logged_in_condition_does_not_hydrate_shield_session() :void {
		$this->loginAsAdministrator();
		$con = $this->requireController();
		unset( $con->this_req->session );

		$condition = ( new IsLoggedInNormal() )->setThisRequest( $con->this_req );

		$this->assertTrue( $condition->run() );
		$this->assertFalse( isset( $con->this_req->session ) );
	}

	public function test_session_activity_persistence_throttles_wordpress_session_token_updates() :void {
		$con = $this->requireController();
		$userID = $this->createAdministratorUser();
		$base = \time() + 10;
		$token = \WP_Session_Tokens::get_instance( $userID )->create( $base + \DAY_IN_SECONDS );

		$this->primeRequestIdentity();

		$this->withFixedRequestTimestamp( $base, function () use ( $con, $userID, $token ) {
			$con->comps->session->buildSession( $userID, $token );
		} );
		$storedAtBase = $this->currentRawSessionForUser( $userID );
		$this->assertSame( $base, (int)$storedAtBase[ 'shield' ][ 'last_activity_at' ] );

		$sessionAtThirty = $this->withFixedRequestTimestamp( $base + 30, function () use ( $con, $userID, $token ) {
			return $con->comps->session->buildSession( $userID, $token );
		} );
		$storedAtThirty = $this->currentRawSessionForUser( $userID );

		$this->assertSame( $storedAtBase, $storedAtThirty );
		$this->assertSame( 30, (int)$sessionAtThirty->shield[ 'idle_interval' ] );
		$this->assertSame( $base + 30, (int)$sessionAtThirty->shield[ 'last_activity_at' ] );

		$this->withFixedRequestTimestamp( $base + 60, function () use ( $con, $userID, $token ) {
			$con->comps->session->buildSession( $userID, $token );
		} );
		$storedAtSixty = $this->currentRawSessionForUser( $userID );

		$this->assertSame( $base + 60, (int)$storedAtSixty[ 'shield' ][ 'last_activity_at' ] );
	}

	public function test_explicit_session_parameter_updates_persist_inside_activity_throttle_window() :void {
		$con = $this->requireController();
		$userID = $this->createAdministratorUser();
		$base = \time() + 10;
		$token = \WP_Session_Tokens::get_instance( $userID )->create( $base + \DAY_IN_SECONDS );

		$this->primeRequestIdentity();

		$session = $this->withFixedRequestTimestamp( $base, function () use ( $con, $userID, $token ) {
			return $con->comps->session->buildSession( $userID, $token );
		} );
		$storedBefore = $this->currentRawSessionForUser( $userID );

		$con->comps->session->updateSessionParameter( $session, 'secadmin_at', $base + 30 );
		$storedAfter = $this->currentRawSessionForUser( $userID );

		$this->assertNotSame( $storedBefore, $storedAfter );
		$this->assertSame( $base + 30, (int)$storedAfter[ 'shield' ][ 'secadmin_at' ] );
	}

	private function primeRequestIdentity() :void {
		$con = $this->requireController();
		$con->this_req->ip = '203.0.113.45';
		$con->this_req->host = 'example.org';
		$con->this_req->useragent = 'Shield Integration Test';
	}

	private function currentRawSessionForUser( int $userID ) :array {
		$raw = \get_user_meta( $userID, 'session_tokens', true );
		$this->assertIsArray( $raw );
		$this->assertCount( 1, $raw );
		$session = \current( $raw );
		$this->assertIsArray( $session );
		return $session;
	}

	private function withFixedRequestTimestamp( int $timestamp, callable $callback ) {
		$ref = new \ReflectionClass( Services::class );
		$servicesProp = $ref->getProperty( 'services' );
		$servicesProp->setAccessible( true );

		$servicesSnapshot = $servicesProp->getValue();
		$services = \is_array( $servicesSnapshot ) ? $servicesSnapshot : [];
		$services[ 'service_request' ] = new class( $timestamp ) extends ServicesRequest {

			private int $fixedTimestamp;

			public function __construct( int $fixedTimestamp ) {
				$this->fixedTimestamp = $fixedTimestamp;
				parent::__construct();
			}

			public function ts( bool $update = true ) :int {
				return $this->fixedTimestamp;
			}
		};

		$servicesProp->setValue( null, $services );

		try {
			return $callback();
		}
		finally {
			$servicesProp->setValue( null, $servicesSnapshot );
		}
	}
}
