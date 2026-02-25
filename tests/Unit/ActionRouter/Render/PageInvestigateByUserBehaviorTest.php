<?php declare( strict_types=1 );

namespace {
	if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
		\define( 'HOUR_IN_SECONDS', 3600 );
	}
	if ( !\class_exists( '\WP_User' ) ) {
		class WP_User {
			public int $ID = 0;
			public string $user_login = '';
			public string $user_email = '';
			public string $display_name = '';
		}
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules {

	if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
		function shield_security_get_plugin() {
			return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
		}
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render {

use Brain\Monkey\Functions;
use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageInvestigateByUser;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Request,
	Users
};

class PageInvestigateByUserBehaviorTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();

		Functions\when( 'sanitize_text_field' )->alias( static fn( $text ) => \is_string( $text ) ? \trim( $text ) : '' );
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'wp_hash' )->alias(
			static fn( string $data, string $scheme = '' ) :string => \hash( 'sha256', $scheme.'|'.$data )
		);
		Functions\when( 'wp_create_nonce' )->alias( static fn( string $action ) :string => 'nonce-'.$action );
		Functions\when( 'get_rest_url' )->alias(
			static fn( $blog = null, string $path = '' ) :string => '/wp-json/'.\ltrim( $path, '/' )
		);
		Functions\when( 'rawurlencode_deep' )->alias(
			static function ( $value ) {
				if ( \is_array( $value ) ) {
					return \array_map(
						static fn( $item ) :string => \rawurlencode( (string)$item ),
						$value
					);
				}
				return \rawurlencode( (string)$value );
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $params, string $url ) :string {
				if ( empty( $params ) ) {
					return $url;
				}
				$pieces = [];
				foreach ( $params as $key => $value ) {
					$pieces[] = $key.'='.$value;
				}
				return $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).\implode( '&', $pieces );
			}
		);

		$this->servicesSnapshot = ServicesState::snapshot();
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_no_lookup_returns_no_subject_flags_and_no_table_contracts() :void {
		$this->installServices();
		$page = new PageInvestigateByUserUnitTestDouble( null, [], [], [] );

		$renderData = $this->invokeGetRenderData( $page );

		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'has_lookup' ] ?? true ) );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'has_subject' ] ?? true ) );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'subject_not_found' ] ?? true ) );
		$this->assertSame( [], $renderData[ 'vars' ][ 'tables' ] ?? [] );
		$this->assertSame(
			[
				'page'    => 'icwp-wpsf-plugin',
				'nav'     => PluginNavs::NAV_ACTIVITY,
				'nav_sub' => PluginNavs::SUBNAV_ACTIVITY_BY_USER,
			],
			$renderData[ 'vars' ][ 'lookup_route' ] ?? []
		);
	}

	public function test_invalid_lookup_sets_subject_not_found_flag() :void {
		$this->installServices( [
			'user_lookup' => 'unknown-user',
		] );
		$page = new PageInvestigateByUserUnitTestDouble( null, [], [], [] );

		$renderData = $this->invokeGetRenderData( $page );

		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'has_lookup' ] ?? false ) );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'has_subject' ] ?? true ) );
		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'subject_not_found' ] ?? false ) );
	}

	public function test_valid_lookup_returns_subject_summary_tables_and_ip_card_contract() :void {
		$user = new \WP_User();
		$user->ID = 42;
		$user->user_login = 'operator';
		$user->user_email = 'operator@example.com';
		$user->display_name = 'Operator User';

		$this->installServices( [
			'user_lookup' => '42',
		] );

		$page = new PageInvestigateByUserUnitTestDouble(
			$user,
			[
				[
					'ip'               => '203.0.113.9',
					'last_activity_ts' => 1200,
				],
				[
					'ip'               => '203.0.113.9',
					'last_activity_ts' => 1000,
				],
			],
			[
				[
					'ip'            => '203.0.113.9',
					'created_at_ts' => 1500,
				],
				[
					'ip'            => '198.51.100.4',
					'created_at_ts' => 1400,
				],
			],
			[
				[
					'ip'            => '198.51.100.4',
					'created_at_ts' => 1700,
					'offense'       => true,
				],
				[
					'ip'            => '192.0.2.1',
					'created_at_ts' => 1600,
					'offense'       => false,
				],
			]
		);

		$renderData = $this->invokeGetRenderData( $page );
		$vars = $renderData[ 'vars' ] ?? [];
		$tables = $vars[ 'tables' ] ?? [];
		$summary = $vars[ 'summary' ] ?? [];

		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'has_subject' ] ?? false ) );
		$this->assertArrayHasKey( 'subject', $vars );
		$this->assertArrayHasKey( 'summary', $vars );
		$this->assertArrayHasKey( 'rail_nav_items', $vars );
		$this->assertArrayHasKey( 'sessions', $tables );
		$this->assertArrayHasKey( 'activity', $tables );
		$this->assertArrayHasKey( 'requests', $tables );

		$this->assertSame( InvestigationTableContract::TABLE_TYPE_SESSIONS, $tables[ 'sessions' ][ 'table_type' ] ?? '' );
		$this->assertSame( InvestigationTableContract::TABLE_TYPE_ACTIVITY, $tables[ 'activity' ][ 'table_type' ] ?? '' );
		$this->assertSame( InvestigationTableContract::TABLE_TYPE_TRAFFIC, $tables[ 'requests' ][ 'table_type' ] ?? '' );
		$this->assertSame( InvestigationTableContract::SUBJECT_TYPE_USER, $tables[ 'sessions' ][ 'subject_type' ] ?? '' );
		$this->assertSame( 42, (int)( $tables[ 'sessions' ][ 'subject_id' ] ?? 0 ) );
		$this->assertSame( 42, (int)( $tables[ 'activity' ][ 'subject_id' ] ?? 0 ) );
		$this->assertSame( 42, (int)( $tables[ 'requests' ][ 'subject_id' ] ?? 0 ) );
		$this->assertSame( 'good', (string)( $summary[ 'sessions' ][ 'status' ] ?? '' ) );
		$this->assertSame( 'warning', (string)( $summary[ 'activity' ][ 'status' ] ?? '' ) );
		$this->assertSame( 'critical', (string)( $summary[ 'requests' ][ 'status' ] ?? '' ) );
		$this->assertSame( 'critical', (string)( $summary[ 'ips' ][ 'status' ] ?? '' ) );

		$activityHref = (string)( $tables[ 'activity' ][ 'full_log_href' ] ?? '' );
		$requestsHref = (string)( $tables[ 'requests' ][ 'full_log_href' ] ?? '' );

		$activityQuery = [];
		$requestsQuery = [];
		\parse_str( (string)\parse_url( $activityHref, \PHP_URL_QUERY ), $activityQuery );
		\parse_str( (string)\parse_url( $requestsHref, \PHP_URL_QUERY ), $requestsQuery );
		$this->assertSame( 'user_id:42', (string)( $activityQuery[ 'search' ] ?? '' ) );
		$this->assertSame( 'user_id:42', (string)( $requestsQuery[ 'search' ] ?? '' ) );

		$relatedIps = $vars[ 'related_ips' ] ?? [];
		$this->assertCount( 3, $relatedIps );
		$this->assertSame(
			[ '198.51.100.4', '192.0.2.1', '203.0.113.9' ],
			\array_column( $relatedIps, 'ip' )
		);

		$this->assertSame(
			[ 'critical', 'warning', 'good' ],
			\array_column( $relatedIps, 'status' )
		);

		$requiredIpKeys = [ 'ip', 'href', 'status', 'last_seen_ts', 'last_seen_at', 'last_seen_ago', 'sessions_count', 'activity_count', 'requests_count' ];
		foreach ( $relatedIps as $card ) {
			foreach ( $requiredIpKeys as $requiredKey ) {
				$this->assertArrayHasKey( $requiredKey, $card );
			}
		}

		$this->assertSame(
			[ 1700, 1600, 1500 ],
			\array_column( $relatedIps, 'last_seen_ts' )
		);
	}

	public function test_summary_ip_status_prefers_warning_over_good_when_no_critical_ip_exists() :void {
		$user = new \WP_User();
		$user->ID = 7;
		$user->user_login = 'analyst';
		$user->user_email = 'analyst@example.com';
		$user->display_name = 'Analyst User';

		$this->installServices( [
			'user_lookup' => '7',
		] );

		$page = new PageInvestigateByUserUnitTestDouble(
			$user,
			[
				[
					'ip'               => '203.0.113.55',
					'last_activity_ts' => 1100,
				],
			],
			[],
			[
				[
					'ip'            => '198.51.100.77',
					'created_at_ts' => 1200,
					'offense'       => false,
				],
			]
		);

		$renderData = $this->invokeGetRenderData( $page );
		$summary = $renderData[ 'vars' ][ 'summary' ] ?? [];
		$this->assertSame( 'warning', (string)( $summary[ 'ips' ][ 'status' ] ?? '' ) );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function rootAdminPageSlug() :string {
				return 'icwp-wpsf-plugin';
			}

			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}

			public function ipAnalysis( string $ip ) :string {
				return '/admin/'.PluginNavs::NAV_IPS.'/'.PluginNavs::SUBNAV_IPS_RULES.'?analyse_ip='.$ip;
			}

			public function investigateByIp( string $ip = '' ) :string {
				return empty( $ip ) ? '/admin/activity/by_ip' : '/admin/activity/by_ip?analyse_ip='.$ip;
			}

			public function investigateByUser( string $lookup = '' ) :string {
				return empty( $lookup ) ? '/admin/activity/by_user' : '/admin/activity/by_user?user_lookup='.$lookup;
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		PluginControllerInstaller::install( $controller );
	}

	private function installServices( array $query = [] ) :void {
		ServicesState::installItems( [
			'service_request'  => new class( $query ) extends Request {
				private array $queryValues;

				public function __construct( array $queryValues = [] ) {
					$this->queryValues = $queryValues;
				}

				public function query( $key, $default = null ) {
					return $this->queryValues[ $key ] ?? $default;
				}

				public function ip() :string {
					return '127.0.0.1';
				}

				public function ts( bool $update = true ) :int {
					return 1700000000;
				}

				public function carbon( $setTimezone = false, bool $userLocale = true ) :Carbon {
					return new Carbon( 'now', 'UTC' );
				}
			},
			'service_wpgeneral' => new class extends General {
				public function ajaxURL() :string {
					return '/admin-ajax.php';
				}

				public function getTimeStringForDisplay( $ts = null, $bShowTime = true, $bShowDate = true ) {
					return 'display:'.(int)$ts;
				}
			},
			'service_wpusers'   => new class extends Users {
				public function getCurrentWpUserId() {
					return 1;
				}
			},
		] );
	}

	private function invokeGetRenderData( PageInvestigateByUser $page ) :array {
		$method = new \ReflectionMethod( $page, 'getRenderData' );
		$method->setAccessible( true );
		return $method->invoke( $page );
	}
}

class PageInvestigateByUserUnitTestDouble extends PageInvestigateByUser {

	private ?\WP_User $subject;
	private array $sessions;
	private array $activityLogs;
	private array $requestLogs;

	public function __construct( ?\WP_User $subject, array $sessions, array $activityLogs, array $requestLogs ) {
		$this->subject = $subject;
		$this->sessions = $sessions;
		$this->activityLogs = $activityLogs;
		$this->requestLogs = $requestLogs;
	}

	protected function resolveSubject( string $lookup ) :?\WP_User {
		return $this->subject;
	}

	protected function buildSessions( \WP_User $subject ) :array {
		return $this->sessions;
	}

	protected function buildActivityLogs( \WP_User $subject ) :array {
		return $this->activityLogs;
	}

	protected function buildRequestLogs( \WP_User $subject ) :array {
		return $this->requestLogs;
	}
}

}
