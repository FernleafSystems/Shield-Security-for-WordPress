<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsController;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Services\Services;

class BotSignalsFrontPageEligibilityIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $requestSnapshot = [];

	private array $frontPageOptions = [];

	public function set_up() {
		parent::set_up();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->frontPageOptions = [
			'show_on_front' => \get_option( 'show_on_front' ),
			'page_on_front' => \get_option( 'page_on_front' ),
		];
	}

	public function tear_down() {
		\update_option( 'show_on_front', $this->frontPageOptions[ 'show_on_front' ] );
		\update_option( 'page_on_front', $this->frontPageOptions[ 'page_on_front' ] );
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_anonymous_front_page_get_updates_frontpage_signal() :void {
		$this->requireDb( 'bot_signals' );
		$this->requireDb( 'ips' );
		\wp_set_current_user( 0 );
		$ip = '198.51.100.201';
		$staleAt = Services::Request()->ts() - HOUR_IN_SECONDS;
		$id = TestDataFactory::insertBotSignal( $ip, [
			'frontpage_at' => $staleAt,
		] );

		$this->goToFrontPage( $ip );
		$this->runFrontPageFooter();

		$this->assertGreaterThan( $staleAt, $this->frontpageAtForId( $id ) );
	}

	public function test_logged_in_front_page_get_does_not_update_frontpage_signal() :void {
		$this->requireDb( 'bot_signals' );
		$this->requireDb( 'ips' );
		$this->loginAsAdministrator();
		$ip = '198.51.100.202';
		$staleAt = Services::Request()->ts() - HOUR_IN_SECONDS;
		$id = TestDataFactory::insertBotSignal( $ip, [
			'frontpage_at' => $staleAt,
		] );

		$this->goToFrontPage( $ip );
		$this->runFrontPageFooter();

		$this->assertSame( $staleAt, $this->frontpageAtForId( $id ) );
	}

	private function goToFrontPage( string $ip ) :void {
		$postID = self::factory()->post->create( [
			'post_type'   => 'page',
			'post_status' => 'publish',
		] );
		\update_option( 'show_on_front', 'page' );
		\update_option( 'page_on_front', $postID );
		$this->go_to( \home_url( '/' ) );
		$this->applyCurrentRequestState(
			[
				'REMOTE_ADDR'     => $ip,
				'REQUEST_METHOD'  => 'GET',
				'REQUEST_URI'     => '/',
			],
			[],
			[],
			[
				'ip'               => $ip,
				'ip_is_public'     => true,
				'is_trusted_request' => false,
				'path'             => '/',
			]
		);
	}

	private function runFrontPageFooter() :void {
		$this->withIsolatedHooks( [
			'wp_footer',
			'init',
			'login_footer',
			'shield/event',
		], function () {
			( new BotSignalsController() )->execute();
			\do_action( 'wp_footer' );
		} );
	}

	private function frontpageAtForId( int $id ) :int {
		$record = $this->requireController()->db_con->bot_signals->getQuerySelector()->byId( $id );
		$this->assertNotEmpty( $record );
		return (int)$record->frontpage_at;
	}

	private function withIsolatedHooks( array $hookNames, callable $callback ) {
		global $wp_filter;

		$snapshot = [];
		foreach ( $hookNames as $hookName ) {
			$snapshot[ $hookName ] = $wp_filter[ $hookName ] ?? null;
			unset( $wp_filter[ $hookName ] );
		}

		try {
			return $callback();
		}
		finally {
			foreach ( $hookNames as $hookName ) {
				if ( $snapshot[ $hookName ] === null ) {
					unset( $wp_filter[ $hookName ] );
				}
				else {
					$wp_filter[ $hookName ] = $snapshot[ $hookName ];
				}
			}
		}
	}
}
