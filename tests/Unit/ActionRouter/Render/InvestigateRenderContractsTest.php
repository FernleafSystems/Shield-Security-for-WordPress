<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\InvestigateRenderContracts;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestGeneral,
	UnitTestPluginUrls,
	UnitTestRequest,
	UnitTestUsers
};

class InvestigateRenderContractsTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
			\define( 'HOUR_IN_SECONDS', 3600 );
		}
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias( static fn( $text ) => \is_string( $text ) ? \strtolower( \trim( $text ) ) : '' );
		Functions\when( 'wp_create_nonce' )->alias( static fn( string $action ) :string => 'nonce-'.$action );
		Functions\when( 'wp_hash' )->alias(
			static fn( string $data, string $scheme = 'auth' ) :string => 'hash-'.$scheme.'-'.$data
		);
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
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest(),
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_wpusers'   => new UnitTestUsers(),
		] );
		UnitTestControllerFactory::install( new UnitTestPluginUrls() );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_normalize_table_contract_applies_defaults_for_missing_keys() :void {
		$normalized = ( new InvestigateRenderContractsTestDouble() )->normalizeTableContract( [
			'title'  => 'Recent Sessions',
			'status' => 'good',
		] );

		$this->assertSame( 'Recent Sessions', $normalized[ 'title' ] ?? '' );
		$this->assertSame( 'good', $normalized[ 'status' ] ?? '' );
		$this->assertSame( 'Full Log', $normalized[ 'full_log_text' ] ?? '' );
		$this->assertSame( 'btn btn-outline-secondary btn-sm', $normalized[ 'full_log_button_class' ] ?? '' );
		$this->assertTrue( $normalized[ 'show_header' ] ?? false );
		$this->assertFalse( $normalized[ 'is_flat' ] ?? true );
		$this->assertFalse( $normalized[ 'is_empty' ] ?? true );
		$this->assertSame( 'info', $normalized[ 'empty_status' ] ?? '' );
		$this->assertSame( '', $normalized[ 'empty_text' ] ?? 'missing' );
	}

	public function test_normalize_table_contract_preserves_explicit_overrides() :void {
		$normalized = ( new InvestigateRenderContractsTestDouble() )->normalizeTableContract( [
			'title'                  => 'Recent Requests',
			'status'                 => 'warning',
			'full_log_text'          => 'Open Requests Log',
			'full_log_button_class'  => 'btn btn-primary btn-sm',
			'show_header'            => false,
			'is_flat'                => true,
			'is_empty'               => true,
			'empty_status'           => 'warning',
			'empty_text'             => 'No request logs were found.',
		] );

		$this->assertSame( 'Recent Requests', $normalized[ 'title' ] ?? '' );
		$this->assertSame( 'warning', $normalized[ 'status' ] ?? '' );
		$this->assertSame( 'Open Requests Log', $normalized[ 'full_log_text' ] ?? '' );
		$this->assertSame( 'btn btn-primary btn-sm', $normalized[ 'full_log_button_class' ] ?? '' );
		$this->assertFalse( $normalized[ 'show_header' ] ?? true );
		$this->assertTrue( $normalized[ 'is_flat' ] ?? false );
		$this->assertTrue( $normalized[ 'is_empty' ] ?? false );
		$this->assertSame( 'warning', $normalized[ 'empty_status' ] ?? '' );
		$this->assertSame( 'No request logs were found.', $normalized[ 'empty_text' ] ?? '' );
	}

	public function test_lookup_behavior_contract_defaults_and_overrides() :void {
		$subject = new InvestigateRenderContractsTestDouble();

		$this->assertSame(
			[
				'panel_form'            => true,
				'use_select2'           => false,
				'auto_submit_on_change' => false,
			],
			$subject->lookupBehavior()
		);

		$this->assertSame(
			[
				'panel_form'            => true,
				'use_select2'           => true,
				'auto_submit_on_change' => true,
			],
			$subject->lookupBehavior( true, true, true )
		);
	}

	public function test_lookup_shortcut_contract_is_normalized() :void {
		$subject = new InvestigateRenderContractsTestDouble();
		$shortcut = $subject->lookupShortcut(
			'self',
			'/admin/activity/by_ip?analyse_ip=203.0.113.8',
			'Look up yourself',
			'navigate',
			'bi bi-globe2'
		);

		$this->assertSame( 'self', $shortcut[ 'key' ] ?? '' );
		$this->assertSame( '/admin/activity/by_ip?analyse_ip=203.0.113.8', $shortcut[ 'href' ] ?? '' );
		$this->assertSame( 'navigate', $shortcut[ 'action_type' ] ?? '' );
		$this->assertSame( 'bi bi-globe2', $shortcut[ 'icon_class' ] ?? '' );
		$this->assertNotSame( '', $shortcut[ 'label' ] ?? '' );
	}

	public function test_lookup_ajax_attr_value_is_producer_encoded() :void {
		$subject = new InvestigateRenderContractsTestDouble();
		$ajax = [
			'subject'              => 'user',
			'minimum_input_length' => 1,
			'delay_ms'             => 700,
			'action'               => [ 'slug' => 'investigate_lookup_select' ],
		];

		$this->assertSame( '', $subject->lookupAjaxAttr( [] ) );
		$this->assertSame( $ajax, \json_decode( $subject->lookupAjaxAttr( $ajax ), true, 512, \JSON_THROW_ON_ERROR ) );
	}

	public function test_lookup_display_contract_defaults_and_overrides() :void {
		$subject = new InvestigateRenderContractsTestDouble();

		$this->assertSame(
			[
				'show_subject_header'      => true,
				'show_lookup_with_subject' => false,
				'change_label'             => '',
			],
			$subject->lookupDisplay()
		);

		$this->assertSame(
			[
				'show_subject_header'      => false,
				'show_lookup_with_subject' => true,
				'change_label'             => 'Change IP address',
			],
			$subject->lookupDisplay( [
				'show_subject_header'      => false,
				'show_lookup_with_subject' => true,
				'change_label'             => 'Change IP address',
			] )
		);
	}

	public function test_subject_header_contract_always_includes_context_step_json() :void {
		$subject = new InvestigateRenderContractsTestDouble();

		$this->assertSame(
			[
				'title'             => 'operator',
				'meta'              => 'operator@example.com',
				'context_step_json' => '',
			],
			$subject->subjectHeader( 'operator', 'operator@example.com' )
		);

		$this->assertSame(
			[
				'title'             => '203.0.113.88',
				'meta'              => '',
				'context_step_json' => '{"title":"203.0.113.88"}',
			],
			$subject->subjectHeader( '203.0.113.88', '', '{"title":"203.0.113.88"}' )
		);
	}

	public function test_controller_backed_route_helpers_build_expected_contracts() :void {
		$subject = new InvestigateRenderContractsTestDouble();

		$this->assertSame(
			[
				'page'    => 'icwp-wpsf-plugin',
				'nav'     => PluginNavs::NAV_ACTIVITY,
				'nav_sub' => PluginNavs::SUBNAV_ACTIVITY_BY_IP,
			],
			$subject->lookupRoute( PluginNavs::SUBNAV_ACTIVITY_BY_IP )
		);
	}

	public function test_build_rail_nav_items_from_tabs_uses_plain_label_when_count_missing() :void {
		$items = ( new InvestigateRenderContractsTestDouble() )->railNavItemsFromTabs( [
			[
				'target'    => '#tabOne',
				'nav_id'    => 'tab-one',
				'controls'  => 'tabOne',
				'label'     => 'Overview',
				'is_active' => true,
			],
			[
				'target'    => '#tabTwo',
				'nav_id'    => 'tab-two',
				'controls'  => 'tabTwo',
				'label'     => 'Activity',
				'count'     => 4,
				'is_active' => false,
			],
		] );

		$this->assertSame( 'Overview', (string)( $items[ 0 ][ 'label' ] ?? '' ) );
		$this->assertSame( 'Activity (4)', (string)( $items[ 1 ][ 'label' ] ?? '' ) );
	}

	public function test_with_empty_state_strips_table_metadata_when_records_do_not_exist() :void {
		$subject = new InvestigateRenderContractsTestDouble();
		$table = $subject->withEmptyState(
			$subject->tableContainerContract(
				'Recent Activity Logs',
				'warning',
				'activity',
				'user',
				'42',
				[ 'columns' => [] ],
				[ 'slug' => 'investigation_table' ],
				'/admin/activity/logs'
			),
			0,
			'No activity records were found for this subject.',
			'warning'
		);

		$this->assertTrue( (bool)( $table[ 'is_empty' ] ?? false ) );
		$this->assertSame( 'warning', (string)( $table[ 'empty_status' ] ?? '' ) );
		$this->assertSame(
			'No activity records were found for this subject.',
			(string)( $table[ 'empty_text' ] ?? '' )
		);
		$this->assertArrayNotHasKey( 'table_type', $table );
		$this->assertArrayNotHasKey( 'subject_type', $table );
		$this->assertArrayNotHasKey( 'subject_id', $table );
		$this->assertArrayNotHasKey( 'datatables_init_attr', $table );
		$this->assertArrayNotHasKey( 'table_action_attr', $table );
	}

	public function test_table_container_contract_omits_empty_full_log_href() :void {
		$table = ( new InvestigateRenderContractsTestDouble() )->tableContainerContract(
			'Recent Activity Logs',
			'warning',
			'activity',
			'plugin',
			'akismet/akismet.php',
			[ 'columns' => [] ],
			[ 'slug' => 'investigation_table' ]
		);

		$this->assertSame( 'Recent Activity Logs', (string)( $table[ 'title' ] ?? '' ) );
		$this->assertSame( 'activity', (string)( $table[ 'table_type' ] ?? '' ) );
		$this->assertSame( 'plugin', (string)( $table[ 'subject_type' ] ?? '' ) );
		$this->assertSame( 'akismet/akismet.php', (string)( $table[ 'subject_id' ] ?? '' ) );
		$this->assertFalse( $table[ 'is_empty' ] );
		$this->assertArrayNotHasKey( 'full_log_href', $table );
		$this->assertSame( [ 'columns' => [] ], $this->decodeJsonAttr( $table[ 'datatables_init_attr' ] ) );
		$this->assertSame( [ 'slug' => 'investigation_table' ], $this->decodeJsonAttr( $table[ 'table_action_attr' ] ) );
	}

	private function decodeJsonAttr( string $json ) :array {
		return $json === '' ? [] : \json_decode( $json, true, 512, \JSON_THROW_ON_ERROR );
	}
}

class InvestigateRenderContractsTestDouble {

	use InvestigateRenderContracts;

	public function normalizeTableContract( array $table ) :array {
		return $this->normalizeInvestigationTableContract( $table );
	}

	public function lookupBehavior( bool $panelForm = true, bool $useSelect2 = false, bool $autoSubmit = false ) :array {
		return $this->buildLookupBehaviorContract( $panelForm, $useSelect2, $autoSubmit );
	}

	public function lookupShortcut(
		string $key,
		string $href,
		string $label,
		string $actionType = 'navigate',
		string $iconClass = ''
	) :array {
		return $this->buildLookupShortcutContract( $key, $href, $label, $actionType, $iconClass );
	}

	public function lookupDisplay( array $display = [] ) :array {
		return $this->normalizeLookupDisplayContract( $display );
	}

	public function lookupAjaxAttr( array $lookupAjax ) :string {
		return $this->buildLookupAjaxAttrValue( $lookupAjax );
	}

	public function subjectHeader( string $title, string $meta = '', string $contextStepJson = '' ) :array {
		return $this->buildSubjectHeaderContract( $title, $meta, $contextStepJson );
	}

	public function withEmptyState( array $table, int $count, string $emptyText, string $emptyStatus = 'info' ) :array {
		return $this->withEmptyStateTableContract( $table, $count, $emptyText, $emptyStatus );
	}

	public function lookupRoute( string $subNav ) :array {
		return $this->buildLookupRouteContract( $subNav );
	}

	public function railNavItemsFromTabs( array $tabs ) :array {
		return $this->buildRailNavItemsFromTabs( $tabs );
	}

	public function tableContainerContract(
		string $title,
		string $status,
		string $tableType,
		string $subjectType,
		string $subjectId,
		array $datatablesInit,
		array $tableAction,
		string $fullLogHref = ''
	) :array {
		return $this->buildTableContainerContract(
			$title,
			$status,
			$tableType,
			$subjectType,
			$subjectId,
			$datatablesInit,
			$tableAction,
			$fullLogHref
		);
	}

}
