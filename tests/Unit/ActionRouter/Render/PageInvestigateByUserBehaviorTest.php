<?php declare( strict_types=1 );

namespace {
	if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
		\define( 'HOUR_IN_SECONDS', 3600 );
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
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\InvestigationTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageInvestigateByUser;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\InvestigateUserLookupBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestGeneral,
	UnitTestPluginUrls,
	UnitTestRequest,
	UnitTestUsers
};

class PageInvestigateByUserBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private array $servicesSnapshot = [];
	private PageInvestigateByUserRecordingPluginUrls $pluginUrls;

	protected function setUp() :void {
		parent::setUp();

		Functions\when( 'sanitize_text_field' )->alias( static fn( $text ) => \is_string( $text ) ? \trim( $text ) : '' );
		Functions\when( 'sanitize_key' )->alias( static fn( $text ) => \is_string( $text ) ? \strtolower( \trim( $text ) ) : '' );
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
					$pieces[] = $key.'='.( \is_array( $value ) ? \rawurlencode( (string)\json_encode( $value ) ) : $value );
				}
				return $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).\implode( '&', $pieces );
			}
		);
		Functions\when( 'get_edit_user_link' )->alias(
			static fn( int $userId ) :string => '/wp-admin/user-edit.php?user_id='.$userId
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
		$page = new PageInvestigateByUserUnitTestDouble( null, [], [], [], new InvestigateUserLookupBuilderTestDouble( false ) );

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );

		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'has_lookup' ] ?? true ) );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'has_subject' ] ?? true ) );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'subject_not_found' ] ?? true ) );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'hrefs' ] ?? [] );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'strings' ] ?? [] );
		$this->assertSame( [], $renderData[ 'vars' ][ 'tables' ] ?? [] );
		$this->assertSame(
			[
				'page'    => 'icwp-wpsf-plugin',
				'nav'     => PluginNavs::NAV_ACTIVITY,
				'nav_sub' => PluginNavs::SUBNAV_ACTIVITY_BY_USER,
			],
			$renderData[ 'vars' ][ 'lookup_route' ] ?? []
		);
		$this->assertSame(
			[
				'panel_form'            => true,
				'use_select2'           => true,
				'auto_submit_on_change' => true,
			],
			$renderData[ 'vars' ][ 'lookup_behavior' ] ?? []
		);
		$this->assertSame(
			[
				'control_id' => 'shield-investigate-user-lookup-user_lookup-control',
				'label_id'   => 'shield-investigate-user-lookup-user_lookup-label',
				'helper_id'  => 'shield-investigate-user-lookup-user_lookup-helper',
			],
			$renderData[ 'vars' ][ 'lookup_field' ] ?? []
		);
		$this->assertSame( 'user', (string)( $renderData[ 'vars' ][ 'lookup_ajax' ][ 'subject' ] ?? '' ) );
		$this->assertSame( 1, (int)( $renderData[ 'vars' ][ 'lookup_ajax' ][ 'minimum_input_length' ] ?? 0 ) );
		$this->assertSame( 700, (int)( $renderData[ 'vars' ][ 'lookup_ajax' ][ 'delay_ms' ] ?? 0 ) );
		$this->assertNotEmpty( $renderData[ 'vars' ][ 'lookup_ajax' ][ 'action' ] ?? [] );
		$this->assertSame(
			$renderData[ 'vars' ][ 'lookup_ajax' ],
			$this->decodedJsonAttr( (string)( $renderData[ 'vars' ][ 'lookup_ajax_attr' ] ?? '' ) )
		);
		$this->assertSame( '', (string)( $renderData[ 'vars' ][ 'offcanvas_history_mode' ] ?? 'missing' ) );
		$shortcut = $renderData[ 'vars' ][ 'lookup_shortcuts' ][ 0 ] ?? [];
		$this->assertSame( 'self', $shortcut[ 'key' ] ?? '' );
		$this->assertSame( '1', (string)( $this->hrefQuery( (string)( $shortcut[ 'href' ] ?? '' ) )[ 'user_lookup' ] ?? '' ) );
		$this->assertSame( 'navigate', $shortcut[ 'action_type' ] ?? '' );
	}

	public function test_invalid_lookup_sets_subject_not_found_flag() :void {
		$this->installServices( [
			'user_lookup' => 'unknown-user',
		] );
		$page = new PageInvestigateByUserUnitTestDouble( null, [], [], [], new InvestigateUserLookupBuilderTestDouble( false ) );

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );

		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'has_lookup' ] ?? false ) );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'has_subject' ] ?? true ) );
		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'subject_not_found' ] ?? false ) );
	}

	public function test_valid_lookup_returns_subject_summary_tables_and_ip_card_contract() :void {
		$user = new \WP_User();
		$user->ID = 42;
		$user->user_login = 'operator';
		$user->user_email = 'operator@example.com';
		$user->display_name = 'operator-display';

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
			],
			new InvestigateUserLookupBuilderTestDouble( false, [], 'user_lookup_label_sentinel' )
		);

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$vars = $renderData[ 'vars' ] ?? [];
		$tables = $vars[ 'tables' ] ?? [];
		$railNavItems = $vars[ 'rail_nav_items' ] ?? [];
		$overviewRows = $vars[ 'overview_rows' ] ?? [];
		$overviewRowsByKey = $this->rowsByKey( $overviewRows );

		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'has_subject' ] ?? false ) );
		$this->assertArrayHasKey( 'user_lookup_label', $vars );
		$this->assertArrayNotHasKey( 'subject', $vars );
		$this->assertArrayNotHasKey( 'summary', $vars );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'hrefs' ] ?? [] );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'strings' ] ?? [] );
		$this->assertArrayHasKey( 'tabs', $vars );
		$this->assertArrayHasKey( 'rail_nav_items', $vars );
		$this->assertArrayHasKey( 'overview_rows', $vars );
		$this->assertSame( 'tab-navlink-user-overview', (string)( $railNavItems[ 0 ][ 'id' ] ?? '' ) );
		$this->assertTrue( (bool)( $railNavItems[ 0 ][ 'is_focus' ] ?? false ) );
		$this->assertSame(
			[
				'tab-navlink-user-overview',
				'tab-navlink-user-sessions',
				'tab-navlink-user-activity',
				'tab-navlink-user-requests',
				'tab-navlink-user-ips',
			],
			\array_column( $railNavItems, 'id' )
		);
		$this->assertSame(
			[ 'overview', 'sessions', 'activity', 'requests', 'ips' ],
			\array_keys( $vars[ 'tabs' ] ?? [] )
		);
		$this->assertArrayHasKey( 'sessions', $tables );
		$this->assertArrayHasKey( 'activity', $tables );
		$this->assertArrayHasKey( 'requests', $tables );

		$this->assertSame( InvestigationTableContract::TABLE_TYPE_SESSIONS, $tables[ 'sessions' ][ 'table_type' ] ?? '' );
		$this->assertSame( InvestigationTableContract::TABLE_TYPE_ACTIVITY, $tables[ 'activity' ][ 'table_type' ] ?? '' );
		$this->assertSame( InvestigationTableContract::TABLE_TYPE_TRAFFIC, $tables[ 'requests' ][ 'table_type' ] ?? '' );
		foreach ( [ 'sessions', 'activity', 'requests' ] as $tableKey ) {
			$datatablesInit = $this->decodedJsonAttr( (string)( $tables[ $tableKey ][ 'datatables_init_attr' ] ?? '' ) );
			$this->assertArrayHasKey( 'columns', $datatablesInit );
			$this->assertArrayHasKey( 'order', $datatablesInit );
			$this->assertArrayHasKey( 'searchPanes', $datatablesInit );

			$tableAction = $this->decodedJsonAttr( (string)( $tables[ $tableKey ][ 'table_action_attr' ] ?? '' ) );
			$this->assertSame( ActionData::FIELD_SHIELD, $tableAction[ ActionData::FIELD_ACTION ] ?? '' );
			$this->assertSame( InvestigationTableAction::SLUG, $tableAction[ ActionData::FIELD_EXECUTE ] ?? '' );
		}
		$this->assertArrayNotHasKey( 'render_item_analysis_attr', $tables[ 'sessions' ] ?? [] );
		$this->assertArrayNotHasKey( 'render_item_analysis_attr', $tables[ 'activity' ] ?? [] );
		$this->assertArrayNotHasKey( 'render_item_analysis_attr', $tables[ 'requests' ] ?? [] );
		$this->assertTrue( (bool)( $tables[ 'sessions' ][ 'is_flat' ] ?? false ) );
		$this->assertFalse( (bool)( $tables[ 'sessions' ][ 'show_header' ] ?? true ) );
		$this->assertFalse( (bool)( $tables[ 'activity' ][ 'show_header' ] ?? true ) );
		$this->assertFalse( (bool)( $tables[ 'requests' ][ 'show_header' ] ?? true ) );
		$this->assertArrayNotHasKey( 'full_log_href', $tables[ 'sessions' ] ?? [] );
		$this->assertArrayNotHasKey( 'full_log_href', $tables[ 'activity' ] ?? [] );
		$this->assertArrayNotHasKey( 'full_log_href', $tables[ 'requests' ] ?? [] );
		$this->assertSame( InvestigationTableContract::SUBJECT_TYPE_USER, $tables[ 'sessions' ][ 'subject_type' ] ?? '' );
		$this->assertSame( 42, (int)( $tables[ 'sessions' ][ 'subject_id' ] ?? 0 ) );
		$this->assertSame( 42, (int)( $tables[ 'activity' ][ 'subject_id' ] ?? 0 ) );
		$this->assertSame( 42, (int)( $tables[ 'requests' ][ 'subject_id' ] ?? 0 ) );
		$this->assertSame(
			[ 'username', 'display_name', 'email', 'role', 'last_login_ip', 'recent_ips', 'shield_status', 'wp_profile' ],
			\array_column( $overviewRows, 'key' )
		);
		$this->assertSame( 'operator', (string)( $overviewRowsByKey[ 'username' ][ 'value' ] ?? '' ) );
		$this->assertSame( 'operator-display', (string)( $overviewRowsByKey[ 'display_name' ][ 'value' ] ?? '' ) );
		$this->assertSame( 'operator@example.com', (string)( $overviewRowsByKey[ 'email' ][ 'value' ] ?? '' ) );
		$this->assertSame( '203.0.113.9', (string)( $overviewRowsByKey[ 'last_login_ip' ][ 'value' ] ?? '' ) );
		$overviewRecentIps = \array_map(
			'trim',
			\explode( ',', (string)( $overviewRowsByKey[ 'recent_ips' ][ 'value' ] ?? '' ) )
		);
		\sort( $overviewRecentIps );
		$this->assertSame( [ '192.0.2.1', '198.51.100.4', '203.0.113.9' ], $overviewRecentIps );
		$this->assertSame(
			'42',
			(string)( $this->hrefQuery( (string)( $overviewRowsByKey[ 'wp_profile' ][ 'value_href' ] ?? '' ) )[ 'user_id' ] ?? '' )
		);

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
		$this->assertSame(
			[ '198.51.100.4', '192.0.2.1', '203.0.113.9' ],
			$this->queryValues( $relatedIps, 'href', 'analyse_ip' )
		);
		$this->assertSame(
			[ '198.51.100.4', '192.0.2.1', '203.0.113.9' ],
			$this->queryValues( $relatedIps, 'investigate_href', 'analyse_ip' )
		);
		$expectedRouteIps = [ '192.0.2.1', '198.51.100.4', '203.0.113.9' ];
		$ipAnalysisCalls = $this->pluginUrls->ipAnalysisCalls;
		$investigateByIpCalls = $this->pluginUrls->investigateByIpCalls;
		\sort( $ipAnalysisCalls );
		\sort( $investigateByIpCalls );
		$this->assertSame( $expectedRouteIps, $ipAnalysisCalls );
		$this->assertSame( $expectedRouteIps, $investigateByIpCalls );

		$requiredIpKeys = [ 'ip', 'href', 'investigate_href', 'status', 'status_label', 'last_seen_ts', 'last_seen_at', 'last_seen_ago', 'sessions_count', 'activity_count', 'requests_count' ];
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

	public function test_overview_rows_include_recent_ips_without_offense_score_noise() :void {
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
			],
			new InvestigateUserLookupBuilderTestDouble( false )
		);

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$overviewRows = $renderData[ 'vars' ][ 'overview_rows' ] ?? [];
		$overviewByKey = $this->rowsByKey( $overviewRows );
		$recentIps = \array_map(
			'trim',
			\explode( ',', (string)( $overviewByKey[ 'recent_ips' ][ 'value' ] ?? '' ) )
		);
		\sort( $recentIps );
		$this->assertSame( [ '198.51.100.77', '203.0.113.55' ], $recentIps );
	}

	public function test_build_overview_context_returns_profile_query_and_status_key_contract() :void {
		$user = new \WP_User();
		$user->ID = 7;
		$user->user_login = 'suspended-user';
		$user->user_email = 'suspended@example.com';
		$user->display_name = 'Suspended User';
		$user->roles = [ 'shop_manager' ];

		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			null,
			(object)[
				'user_metas' => new class {
					public function for( \WP_User $user ) :object {
						return (object)[
							'record' => (object)[
								'hard_suspended_at' => 1234,
								'ip_ref'            => 0,
							],
						];
					}
				},
				'db_con' => (object)[
					'ips' => new class {
						public function getQuerySelector() :object {
							return new class {
								public function byId( int $id ) {
									return null;
								}
							};
						}
					},
				],
			]
		);

		$page = new class extends PageInvestigateByUser {
		};

		$context = $this->invokeNonPublicMethod(
			$page,
			'buildOverviewContext',
			[
				$user,
				[
					[
						'ip'               => '203.0.113.55',
						'last_activity_ts' => 1100,
					],
				],
				[],
			]
		);

		$this->assertSame( '203.0.113.55', (string)( $context[ 'last_login_ip' ] ?? '' ) );
		$this->assertArrayHasKey( 'shield_status', $context );
		$this->assertSame( '7', (string)( $this->hrefQuery( (string)( $context[ 'wp_profile_href' ] ?? '' ) )[ 'user_id' ] ?? '' ) );
	}

	public function test_render_data_includes_lookup_helper_string() :void {
		$this->installServices();
		$page = new PageInvestigateByUserUnitTestDouble( null, [], [], [], new InvestigateUserLookupBuilderTestDouble( false ) );

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );

		$this->assertArrayHasKey( 'lookup_helper', $renderData[ 'strings' ] ?? [] );
	}

	public function test_small_user_sites_use_static_select2_options_without_ajax() :void {
		$this->installServices();
		$page = new PageInvestigateByUserUnitTestDouble(
			null,
			[],
			[],
			[],
			new InvestigateUserLookupBuilderTestDouble(
				true,
				[
					[
						'value' => '12',
						'label' => '[ID:12 | Author] small-site-user | small@example.com',
					],
				]
			)
		);

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );

		$this->assertSame( [], $renderData[ 'vars' ][ 'lookup_ajax' ] ?? null );
		$this->assertSame( '', (string)( $renderData[ 'vars' ][ 'lookup_ajax_attr' ] ?? 'missing' ) );
		$this->assertSame(
			[ '12' ],
			\array_column( $renderData[ 'vars' ][ 'user_options' ] ?? [], 'value' )
		);
		$this->assertArrayHasKey( 'label', $renderData[ 'vars' ][ 'user_options' ][ 0 ] ?? [] );
	}

	private function rowsByKey( array $rows ) :array {
		$indexed = [];
		foreach ( $rows as $row ) {
			$key = (string)( $row[ 'key' ] ?? '' );
			if ( $key !== '' ) {
				$indexed[ $key ] = $row;
			}
		}
		return $indexed;
	}

	private function queryValues( array $items, string $hrefKey, string $queryKey ) :array {
		return \array_map(
			fn( array $item ) :string => (string)( $this->hrefQuery( (string)( $item[ $hrefKey ] ?? '' ) )[ $queryKey ] ?? '' ),
			$items
		);
	}

	private function hrefQuery( string $href ) :array {
		$query = [];
		\parse_str( (string)\parse_url( $href, \PHP_URL_QUERY ), $query );
		return $query;
	}

	private function decodedJsonAttr( string $attr ) :array {
		$decoded = \json_decode( $attr, true );
		return \is_array( $decoded ) ? $decoded : [];
	}

	private function installControllerStub() :void {
		$this->pluginUrls = new PageInvestigateByUserRecordingPluginUrls();
		UnitTestControllerFactory::install(
			$this->pluginUrls
		);
	}

	private function installServices( array $query = [] ) :void {
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest( $query ),
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_wpusers'   => new UnitTestUsers(),
		] );
	}

}

class PageInvestigateByUserUnitTestDouble extends PageInvestigateByUser {

	private ?\WP_User $subject;
	private array $sessions;
	private array $activityLogs;
	private array $requestLogs;
	private InvestigateUserLookupBuilder $userLookupBuilder;

	public function __construct( ?\WP_User $subject, array $sessions, array $activityLogs, array $requestLogs, InvestigateUserLookupBuilder $userLookupBuilder ) {
		$this->subject = $subject;
		$this->sessions = $sessions;
		$this->activityLogs = $activityLogs;
		$this->requestLogs = $requestLogs;
		$this->userLookupBuilder = $userLookupBuilder;
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

	protected function buildOverviewContext( \WP_User $subject, array $sessions, array $relatedIps ) :array {
		$recentIps = \array_values( \array_unique( \array_map(
			static fn( array $card ) :string => (string)( $card[ 'ip' ] ?? '' ),
			$relatedIps
		) ) );

		return [
			'role'            => 'Unknown',
			'last_login_ip'   => (string)( $sessions[ 0 ][ 'ip' ] ?? 'Unknown' ),
			'recent_ips'      => \array_values( \array_filter( $recentIps ) ),
			'shield_status'   => 'Active',
			'wp_profile_href' => '/wp-admin/user-edit.php?user_id='.$subject->ID,
		];
	}

	protected function getUserLookupBuilder() :InvestigateUserLookupBuilder {
		return $this->userLookupBuilder;
	}
}

class PageInvestigateByUserRecordingPluginUrls extends UnitTestPluginUrls {

	public array $ipAnalysisCalls = [];
	public array $investigateByIpCalls = [];

	public function ipAnalysis( string $ip ) :string {
		$this->ipAnalysisCalls[] = $ip;
		return parent::ipAnalysis( $ip );
	}

	public function investigateByIp( string $ip = '' ) :string {
		$this->investigateByIpCalls[] = $ip;
		return parent::investigateByIp( $ip );
	}
}

class InvestigateUserLookupBuilderTestDouble extends InvestigateUserLookupBuilder {

	private bool $useStaticLookup;
	private array $staticOptions;
	private string $formattedLabel;

	public function __construct( bool $useStaticLookup, array $staticOptions = [], string $formattedLabel = '' ) {
		$this->useStaticLookup = $useStaticLookup;
		$this->staticOptions = $staticOptions;
		$this->formattedLabel = $formattedLabel;
	}

	public function shouldUseStaticSelect( int $threshold = 10 ) :bool {
		return $this->useStaticLookup;
	}

	public function buildStaticOptions( int $limit = 10 ) :array {
		return $this->staticOptions;
	}

	public function formatLabel( \WP_User $user ) :string {
		return $this->formattedLabel !== '' ? $this->formattedLabel : parent::formatLabel( $user );
	}
}

}
