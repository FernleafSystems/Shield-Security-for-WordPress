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
	UnitTestControllerFactory,
	UnitTestPluginUrls
};

class InvestigateRenderContractsTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias( static fn( $text ) => \is_string( $text ) ? \strtolower( \trim( $text ) ) : '' );
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
		UnitTestControllerFactory::install( new UnitTestPluginUrls() );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
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
		$this->assertSame(
			'/admin/activity/logs?search=user_id%3A42',
			$subject->fullLogHrefWithSearch( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS, 'user_id:42' )
		);
	}

	public function test_with_empty_state_preserves_table_metadata_when_records_exist() :void {
		$table = ( new InvestigateRenderContractsTestDouble() )->withEmptyState( [
			'title'               => 'File Scan Status',
			'status'              => 'warning',
			'table_type'          => 'file_scan_results',
			'subject_type'        => 'core',
			'subject_id'          => 'core',
			'datatables_init'     => [ 'columns' => [] ],
			'table_action'        => [ 'slug' => 'investigation_table' ],
			'scan_results_action' => [ 'slug' => 'scan_results_table' ],
		], 2, 'No file scan status records were found for this subject.' );

		$this->assertFalse( (bool)( $table[ 'is_empty' ] ?? true ) );
		$this->assertSame( 'file_scan_results', (string)( $table[ 'table_type' ] ?? '' ) );
		$this->assertSame( 'core', (string)( $table[ 'subject_type' ] ?? '' ) );
		$this->assertSame( 'core', (string)( $table[ 'subject_id' ] ?? '' ) );
		$this->assertArrayHasKey( 'datatables_init', $table );
		$this->assertArrayHasKey( 'table_action', $table );
		$this->assertArrayHasKey( 'scan_results_action', $table );
	}

	public function test_with_empty_state_strips_table_metadata_when_records_do_not_exist() :void {
		$table = ( new InvestigateRenderContractsTestDouble() )->withEmptyState( [
			'title'               => 'File Scan Status',
			'status'              => 'warning',
			'table_type'          => 'file_scan_results',
			'subject_type'        => 'plugin',
			'subject_id'          => 'akismet/akismet.php',
			'datatables_init'     => [ 'columns' => [] ],
			'table_action'        => [ 'slug' => 'investigation_table' ],
			'scan_results_action' => [ 'slug' => 'scan_results_table' ],
		], 0, 'No file scan status records were found for this subject.', 'warning' );

		$this->assertTrue( (bool)( $table[ 'is_empty' ] ?? false ) );
		$this->assertSame( 'warning', (string)( $table[ 'empty_status' ] ?? '' ) );
		$this->assertSame(
			'No file scan status records were found for this subject.',
			(string)( $table[ 'empty_text' ] ?? '' )
		);
		$this->assertArrayNotHasKey( 'table_type', $table );
		$this->assertArrayNotHasKey( 'subject_type', $table );
		$this->assertArrayNotHasKey( 'subject_id', $table );
		$this->assertArrayNotHasKey( 'datatables_init', $table );
		$this->assertArrayNotHasKey( 'table_action', $table );
		$this->assertArrayHasKey( 'scan_results_action', $table );
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

	public function withEmptyState( array $table, int $count, string $emptyText, string $emptyStatus = 'info' ) :array {
		return $this->withEmptyStateTableContract( $table, $count, $emptyText, $emptyStatus );
	}

	public function lookupRoute( string $subNav ) :array {
		return $this->buildLookupRouteContract( $subNav );
	}

	public function fullLogHrefWithSearch( string $nav, string $subNav, string $search ) :string {
		return $this->buildFullLogHrefWithSearch( $nav, $subNav, $search );
	}
}
