<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ActionsQueueScanRailMetrics;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\Malware as MalwarePane;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\Plugins as PluginsPane;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\Themes as ThemesPane;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\Wordpress as WordpressPane;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueDrillDownDetail;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueDrillDownGroups;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\DetailExpansionType;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageActionsQueueLanding;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	ModeLandingAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;
use FernleafSystems\Wordpress\Services\Services;

class ActionsQueueLandingPageIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions;
	use ModeLandingAssertions;
	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
		$this->requireDb( 'file_locker' );
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp' ] );
		$this->resetScanResultCountMemoization();
		\delete_site_transient( 'update_plugins' );
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			static::con()->comps->file_locker->clearLocks();
		}
		\delete_site_transient( 'update_plugins' );
		parent::tear_down();
	}

	private function setPluginUpdateAvailable() :void {
		$updates = new \stdClass();
		$updates->response = [
			self::con()->base_file => (object)[
				'plugin'      => self::con()->base_file,
				'new_version' => self::con()->cfg->version().'.1',
			],
		];
		\set_site_transient( 'update_plugins', $updates );
	}

	private function renderActionsQueueLandingPage() :array {
		return $this->processActionPayloadWithAdminBypass( PageActionsQueueLanding::SLUG, [
			Constants::NAV_ID     => PluginNavs::NAV_SCANS,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_SCANS_OVERVIEW,
		] );
	}

	private function enableAssetScanFixture( array $scanAreas ) :void {
		$this->enablePremiumCapabilities( [
			'scan_pluginsthemes_local',
			'scan_vulnerabilities',
		] );

		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'enable_wpvuln_scan', 'Y' )
			 ->optSet( 'enabled_scan_apc', 'Y' )
			 ->optSet( 'file_scan_areas', $scanAreas )
			 ->store();

		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
		$this->resetScanResultCountMemoization();
	}

	private function seedCriticalAssetAndVulnerabilityQueue() :void {
		$this->enableAssetScanFixture( [ 'wp', 'plugins', 'themes' ] );

		$pluginSlug = self::con()->base_file;
		$themeSlug = \wp_get_theme()->get_stylesheet();

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'      => 'plugin-file.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'     => 'theme-file.php',
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );

		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultItem( $wpvId, [
			'item_id'       => $pluginSlug,
			'is_vulnerable' => 1,
		] );
		$this->resetScanResultCountMemoization();
	}

	private function findZoneTile( array $zoneTiles, string $key ) :array {
		$matches = \array_values( \array_filter(
			$zoneTiles,
			static fn( array $tile ) :bool => (string)( $tile[ 'key' ] ?? '' ) === $key
		) );
		$this->assertCount( 1, $matches, 'Expected exactly one zone tile for '.$key );
		return $matches[ 0 ] ?? [];
	}

	private function insertFileLockRecord( string $type, string $path, int $detectedAt = 0 ) :void {
		$handler = $this->requireDb( 'file_locker' );
		$record = $handler->getRecord();
		$record->type = $type;
		$record->path = $path;
		$record->hash_original = \sha1( $type.'-original' );
		$record->hash_current = \sha1( $type.'-current' );
		$record->public_key_id = 1;
		$record->cipher = 'aes-256-cbc';
		$record->content = 'encrypted-content-'.$type;
		$record->detected_at = $detectedAt;
		$handler->getQueryInserter()->insert( $record );
	}

	/**
	 * @return array{count:int,status:string}
	 */
	private function getMaintenanceQueueMetricsFromLanding() :array {
		$payload = $this->renderActionsQueueLandingPage();
		$vars = \is_array( $payload[ 'render_data' ][ 'vars' ] ?? null ) ? $payload[ 'render_data' ][ 'vars' ] : [];
		$maintenance = $this->findZoneTile(
			\is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [],
			'maintenance'
		);

		return [
			'count'  => (int)( $maintenance[ 'total_issues' ] ?? 0 ),
			'status' => (string)( $maintenance[ 'status' ] ?? 'good' ),
		];
	}

	private function assertFlatEmptyStatePaneWithoutInvestigationTable( \DOMXPath $xpath, string $label ) :void {
		$this->assertXPathExists(
			$xpath,
			'//section[contains(concat(" ", normalize-space(@class), " "), " investigate-table-panel--flat ")]//*[contains(concat(" ", normalize-space(@class), " "), " alert-info ")]',
			$label.' should use the shared flat empty-state alert container'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-investigation-table="1"]',
			0,
			$label.' should not emit an investigation table contract'
		);
	}

	private function assertDisabledPaneWithoutInvestigationTable( \DOMXPath $xpath, string $label ) :void {
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-scan-pane-disabled="1"]',
			$label.' should show the shared disabled callout'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " shield-scan-pane-empty ")]',
			0,
			$label.' should not fall through to the standard empty state'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-investigation-table="1"]',
			0,
			$label.' should not emit an investigation table contract'
		);
	}

	private function assertInvestigationTableContractPresent(
		\DOMXPath $xpath,
		string $tableType,
		string $subjectType,
		string $subjectId,
		string $label
	) :void {
		$this->assertXPathExists(
			$xpath,
			'//*[@data-investigation-table="1" and @data-table-type="'.$tableType.'" and @data-subject-type="'.$subjectType.'" and @data-subject-id="'.$subjectId.'"]',
			$label.' should use the shared investigation table contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-investigation-table="1" and string-length(@data-datatables-init) > 0 and string-length(@data-table-action) > 0 and string-length(@data-scan-results-action) > 0 and string-length(@data-render-item-analysis) > 0]',
			$label.' should include the AJAX and action metadata required by the shared investigation table bootstrap'
		);
	}

	public function test_actions_queue_landing_renders_all_clear_without_drill_shell_when_queue_is_empty() :void {
		TestDataFactory::insertCompletedScan( 'afs', \time() - 7200 );

		$payload = $this->renderActionsQueueLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing baseline state' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$strip = \is_array( $vars[ 'severity_strip' ] ?? null ) ? $vars[ 'severity_strip' ] : [];
		$zoneTiles = \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [];

		$this->assertModeShellPayload( $vars, 'actions', 'critical', false );
		$this->assertModePanelPayload( $vars, '', false );
		$this->assertIsString( $strip[ 'subtext' ] ?? null );
		$this->assertCount( 2, $zoneTiles );
		$this->assertCount(
			2,
			\array_values( \array_filter( $zoneTiles, static fn( array $tile ) :bool => (bool)( $tile[ 'is_enabled' ] ?? false ) ) )
		);
		$this->assertNotEmpty( $this->findZoneTile( $zoneTiles, 'scans' )[ 'assessment_rows' ] ?? [] );
		$this->assertNotEmpty( $this->findZoneTile( $zoneTiles, 'maintenance' )[ 'assessment_rows' ] ?? [] );
		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'queue_is_empty' ] ?? false ) );
		$this->assertNotEmpty( $vars[ 'actions_queue_ajax' ] ?? [] );
		$xpath = $this->createDomXPathFromHtml( $html );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-queue-section="all-clear-context"]',
			'Empty actions queue should render the existing all-clear card'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-drill-shell="1"]',
			0,
			'Empty actions queue should not render the drill-down shell'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-drill-context-card]',
			0,
			'Empty actions queue should not render the drill context card'
		);
		$this->assertNotSame( '', \trim( $html ) );
	}

	public function test_actions_queue_landing_renders_drill_shell_and_bucket_cards_when_queue_has_items() :void {
		$this->setPluginUpdateAvailable();

		$payload = $this->renderActionsQueueLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing maintenance state' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$strip = \is_array( $vars[ 'severity_strip' ] ?? null ) ? $vars[ 'severity_strip' ] : [];
		$zoneTiles = \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [];
		$maintenance = $this->findZoneTile( $zoneTiles, 'maintenance' );
		$scans = $this->findZoneTile( $zoneTiles, 'scans' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertModeShellPayload( $vars, 'actions', 'critical', false );
		$this->assertModePanelPayload( $vars, '', false );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'queue_is_empty' ] ?? true ) );
		$this->assertSame( '', (string)( $strip[ 'subtext' ] ?? '' ) );
		$this->assertCount( 2, $zoneTiles );
		$this->assertTrue( (bool)( $maintenance[ 'is_enabled' ] ?? false ) );
		$this->assertFalse( (bool)( $maintenance[ 'is_disabled' ] ?? true ) );
		$this->assertSame( 'maintenance', (string)( $maintenance[ 'panel_target' ] ?? '' ) );
		$this->assertNotEmpty( $maintenance[ 'assessment_rows' ] ?? [] );
		$this->assertSame( [ 'warning', 'good' ], \array_column( $maintenance[ 'maintenance_detail_groups' ] ?? [], 'status' ) );
		$this->assertTrue( (bool)( $scans[ 'is_enabled' ] ?? false ) );
		$this->assertFalse( (bool)( $scans[ 'has_issues' ] ?? true ) );
		$this->assertNotEmpty( $scans[ 'assessment_rows' ] ?? [] );
		$maintenanceItemsByKey = [];
		foreach ( $maintenance[ 'items' ] ?? [] as $item ) {
			$maintenanceItemsByKey[ (string)( $item[ 'key' ] ?? '' ) ] = $item;
		}
		$this->assertSame(
			DetailExpansionType::SIMPLE_TABLE,
			(string)( $maintenanceItemsByKey[ 'wp_plugins_updates' ][ 'expansion' ][ 'type' ] ?? '' )
		);
		$this->assertNotEmpty( $maintenanceItemsByKey[ 'wp_plugins_updates' ][ 'expansion' ][ 'table' ][ 'rows' ] ?? [] );
		$this->assertNotEmpty( $maintenanceItemsByKey[ 'wp_plugins_updates' ][ 'toggle_action' ] ?? [] );
		$this->assertSame( ActionsQueueDrillDownGroups::SLUG, (string)( $vars[ 'actions_queue_ajax' ][ 'groups_render_action' ][ 'render_slug' ] ?? '' ) );
		$this->assertSame( ActionsQueueDrillDownDetail::SLUG, (string)( $vars[ 'actions_queue_ajax' ][ 'detail_render_action' ][ 'render_slug' ] ?? '' ) );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-context-card="actions_drill_shell"]',
			'Actions queue should render the drill context card beside the shell'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-context-card="actions_drill_shell"]//*[contains(concat(" ", normalize-space(@class), " "), " drill-context-card__header-label ") and normalize-space()="Where you are"]',
			'Actions queue should render the shared context card header label'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-context-card="actions_drill_shell"]//*[contains(concat(" ", normalize-space(@class), " "), " drill-context-card__section-label ") and normalize-space()="Focus"]',
			'Actions queue should label the focus section'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-context-card="actions_drill_shell"]//*[contains(concat(" ", normalize-space(@class), " "), " drill-context-card__section-label ") and normalize-space()="Next step"]',
			'Actions queue should label the next-step section'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-shell="1" and @data-drill-shell-mode="actions"]',
			'Actions queue should render the drill-down shell when the queue has items'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-landing="1" and string-length(@data-actions-queue-groups-action) > 0 and string-length(@data-actions-queue-detail-action) > 0]',
			'Actions queue should render PHP-prepared AJAX action JSON on the landing root'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-layer-key="buckets" and string-length(@data-drill-layer-context) > 0]',
			'The shared drill shell should render PHP-prepared layer context JSON'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-layer-key="buckets"]//*[@data-drill-strip="1" and @data-drill-strip-aria-prefix="Back to"]',
			'The shared drill strip should render the PHP-provided aria prefix'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-queue-section="drilldown"]/div[1][@data-drill-shell="1"]',
			'Actions queue should render the drill shell first for mobile-first stacking'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-queue-section="drilldown"]/div[2][@data-drill-context-card="actions_drill_shell"]',
			'Actions queue should render the context card second in the drilldown container'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " actions-queue-buckets__heading ") and normalize-space()="Choose where to start"]',
			'Bucket layer should render the guidance heading'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-drill-target="groups" and @data-drill-bucket-selection]',
			2,
			'Bucket layer should render the two triage bucket cards'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-drill-target="groups" and string-length(@data-drill-bucket-selection) > 0]',
			'Bucket layer should render PHP-prepared selection JSON for drill interactions'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " actions-queue-bucket-card__preview ")]',
			'Bucket layer should surface at least one top-item preview'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " item-box--good ")]',
			'Bucket layer should render the looking-good item box when healthy assessment rows exist'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " item-box__header-title ") and normalize-space()="Looking good"]',
			'Bucket layer should label the healthy summary section'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-shield-rail-target]',
			0,
			'The migrated landing should not render the old scan-results rail sidebar'
		);
		$this->assertNotEmpty( \trim( $html ) );
	}

	public function test_maintenance_panel_exposes_updates_href() :void {
		$this->setPluginUpdateAvailable();

		$payload = $this->renderActionsQueueLandingPage();
		$this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing maintenance ctas' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$maintenance = $this->findZoneTile( \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [], 'maintenance' );
		$itemsByKey = [];
		foreach ( $maintenance[ 'items' ] ?? [] as $item ) {
			$itemsByKey[ (string)( $item[ 'key' ] ?? '' ) ] = $item;
		}

		$this->assertNotSame( '', (string)( $itemsByKey[ 'wp_plugins_updates' ][ 'cta' ][ 'href' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $itemsByKey[ 'wp_plugins_updates' ][ 'cta' ][ 'label' ] ?? '' ) );
		$this->assertSame(
			self::con()->plugin_urls->actionsQueueScans(),
			(string)( $renderData[ 'hrefs' ][ 'scan_results' ] ?? '' )
		);
		$this->assertSame( Services::WpGeneral()->getAdminUrl_Updates(), (string)( $renderData[ 'hrefs' ][ 'wp_updates' ] ?? '' ) );
	}

	public function test_maintenance_panel_inactive_plugin_rows_link_to_filtered_plugins_screen() :void {
		$pluginFile = $this->requireAtLeastInactivePlugins( 1 )[ 0 ];

		$payload = $this->renderActionsQueueLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing inactive plugin links' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//*[@id="maintenance-expand-wp_plugins_inactive"]//a[@href="/wp-admin/plugins.php?s='.rawurlencode( $pluginFile ).'" and @data-bs-title="Manage this plugin" and contains(concat(" ", normalize-space(@class), " "), " actions-landing__table-icon-action ")]',
			'Inactive plugin maintenance rows should render the filtered plugins admin link as an icon action with tooltip'
		);
		$this->assertSame(
			0,
			$xpath->query( '//*[@id="maintenance-expand-wp_plugins_inactive"]//a[contains(@href, "action=activate")]' )->length,
			'Inactive plugin maintenance rows should not offer activation links'
		);
	}

	public function test_groups_ajax_returns_bucket_groups_and_context() :void {
		$this->setPluginUpdateAvailable();

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket' => 'review',
		] );
		$html = (string)( $payload[ 'html' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame( 'Review next - 1 item', (string)( $payload[ 'strip_text' ] ?? '' ) );
		$this->assertSame( '1 item', (string)( $payload[ 'strip_badge' ] ?? '' ) );
		$this->assertSame( 'warning', (string)( $payload[ 'strip_badge_status' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'landing_refresh', $payload );
		$this->assertSame( 'review', (string)( $payload[ 'bucket_selection' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'Review next', (string)( $payload[ 'bucket_selection' ][ 'label' ] ?? '' ) );
		$this->assertSame(
			[
				'path'      => [ 'Triage buckets', 'Review next' ],
				'focus'     => 'Review next contains 1 item that still needs attention.',
				'next_step' => 'Choose a group to review the matching results.',
			],
			$payload[ 'context' ] ?? []
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-queue-groups="1"]',
			'Groups AJAX should render the selected bucket wrapper'
		);
		$this->assertCount( 1, $payload[ 'groups' ] ?? [] );
		$this->assertSame( 'category', (string)( $payload[ 'groups' ][ 0 ][ 'card_type' ] ?? '' ) );
		$this->assertNotSame( 'maintenance', (string)( $payload[ 'groups' ][ 0 ][ 'key' ] ?? '' ) );
		$this->assertNotEmpty( $payload[ 'groups' ][ 0 ][ 'management_link' ] ?? [] );
		$this->assertXPathCount(
			$xpath,
			'//button[@data-drill-target="detail" and @data-drill-group-selection]',
			0,
			'Groups AJAX should not render a detail drill target for category cards'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " item-box__row ")]',
			'Groups AJAX should render maintenance category rows inside the item-box'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " item-box__header-link ")]',
			'Groups AJAX should render the maintenance management link in the category header'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " bi-arrow-right-circle ")]',
			0,
			'Groups AJAX should not render the legacy next-move arrow icon for category cards'
		);
	}

	public function test_groups_ajax_can_refresh_the_current_selected_group_summary() :void {
		$this->setPluginUpdateAvailable();
		$initialPayload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket' => 'review',
		] );
		$selectedGroupKey = (string)( $initialPayload[ 'groups' ][ 0 ][ 'key' ] ?? '' );
		$this->assertNotSame( '', $selectedGroupKey );

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket'                  => 'review',
			'group'                   => $selectedGroupKey,
			'include_landing_refresh' => 1,
		] );

		$this->assertSame( $selectedGroupKey, (string)( $payload[ 'selected_group' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'maintenance', (string)( $payload[ 'selected_group' ][ 'detail_shell' ] ?? '' ) );
		$this->assertSame(
			\sprintf( '%s - 1 item', (string)( $payload[ 'selected_group' ][ 'label' ] ?? '' ) ),
			(string)( $payload[ 'selected_group' ][ 'strip_text' ] ?? '' )
		);
		$this->assertSame( '1 item', (string)( $payload[ 'selected_group' ][ 'strip_badge' ] ?? '' ) );
		$this->assertSame( 1, (int)( $payload[ 'selected_group' ][ 'item_count' ] ?? 0 ) );
		$this->assertFalse( (bool)( $payload[ 'landing_refresh' ][ 'queue_is_empty' ] ?? true ) );
		$this->assertNotSame( '', (string)( $payload[ 'landing_refresh' ][ 'severity_strip_html' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $payload[ 'landing_refresh' ][ 'buckets_html' ] ?? '' ) );
		$this->assertSame( 'review', (string)( $payload[ 'bucket_selection' ][ 'key' ] ?? '' ) );
		$this->assertSame(
			[ 'Triage buckets', 'Review next', (string)( $payload[ 'selected_group' ][ 'label' ] ?? '' ) ],
			$payload[ 'selected_group' ][ 'context' ][ 'path' ] ?? []
		);
		$this->assertNotSame( '', (string)( $payload[ 'selected_group' ][ 'context' ][ 'focus' ] ?? '' ) );
		$this->assertSame(
			'Review the maintenance item and address it in the next appropriate maintenance window.',
			(string)( $payload[ 'selected_group' ][ 'context' ][ 'next_step' ] ?? '' )
		);
	}

	public function test_groups_ajax_renders_finding_cards_for_critical_bucket_groups() :void {
		$this->seedCriticalAssetAndVulnerabilityQueue();

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket' => 'critical',
		] );
		$html = (string)( $payload[ 'html' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame( 'Fix now - 3 items', (string)( $payload[ 'strip_text' ] ?? '' ) );
		$this->assertSame( '3 items', (string)( $payload[ 'strip_badge' ] ?? '' ) );
		$this->assertSame( 'critical', (string)( $payload[ 'strip_badge_status' ] ?? '' ) );
		$this->assertSame( 'critical', (string)( $payload[ 'bucket_selection' ][ 'status' ] ?? '' ) );
		$this->assertCount( 3, $payload[ 'groups' ] ?? [] );
		$this->assertSame( [ 'linked', 'expandable', 'expandable' ], \array_column( $payload[ 'groups' ] ?? [], 'card_type' ) );
		$this->assertSame( [ 'Known Vulnerabilities', 'Plugin Files', 'Theme Files' ], \array_column( $payload[ 'groups' ] ?? [], 'heading_label' ) );
		$this->assertSame( [ '', 'View 1 files', 'View 1 files' ], \array_column( $payload[ 'groups' ] ?? [], 'drill_hint' ) );
		$this->assertStringStartsWith( 'vulnerabilities:', (string)( $payload[ 'groups' ][ 0 ][ 'key' ] ?? '' ) );
		$this->assertSame( 'plugins:'.self::con()->base_file, (string)( $payload[ 'groups' ][ 1 ][ 'key' ] ?? '' ) );
		$this->assertSame( 'themes:'.\wp_get_theme()->get_stylesheet(), (string)( $payload[ 'groups' ][ 2 ][ 'key' ] ?? '' ) );
		$this->assertCount( 2, $payload[ 'groups' ][ 0 ][ 'links' ] ?? [] );
		$this->assertSame( '/wp-admin/plugins.php', (string)( $payload[ 'groups' ][ 0 ][ 'links' ][ 0 ][ 'href' ] ?? '' ) );
		$this->assertSame(
			'https://clk.shldscrty.com/shieldvulnerabilitylookup?type=plugin&slug='.self::con()->base_file.'&version='.self::con()->cfg->version(),
			(string)( $payload[ 'groups' ][ 0 ][ 'links' ][ 1 ][ 'href' ] ?? '' )
		);
		$this->assertSame( '_blank', (string)( $payload[ 'groups' ][ 0 ][ 'links' ][ 1 ][ 'target' ] ?? '' ) );
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " finding-group__heading ")]',
			3,
			'Critical groups AJAX should render one heading per finding group'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " finding-card ")]',
			3,
			'Critical groups AJAX should render finding-card containers for all non-category groups'
		);
		$this->assertXPathCount(
			$xpath,
			'//button[contains(concat(" ", normalize-space(@class), " "), " finding-card--expandable ") and @data-drill-target="detail" and @data-drill-group-selection]',
			2,
			'Only expandable finding cards should emit detail drill attributes'
		);
		$this->assertXPathCount(
			$xpath,
			'//a[contains(concat(" ", normalize-space(@class), " "), " finding-card__link ")]',
			2,
			'Linked vulnerability cards should render the native action links inline'
		);
		$this->assertXPathExists(
			$xpath,
			'//a[contains(concat(" ", normalize-space(@class), " "), " finding-card__link ") and @href="/wp-admin/plugins.php" and not(@target)]',
			'Linked vulnerability cards should render the native plugin-management link inline'
		);
		$this->assertXPathExists(
			$xpath,
			'//a[contains(concat(" ", normalize-space(@class), " "), " finding-card__link ") and @href="https://clk.shldscrty.com/shieldvulnerabilitylookup?type=plugin&amp;slug='.self::con()->base_file.'&amp;version='.self::con()->cfg->version().'" and @target="_blank" and @rel="noopener noreferrer"]',
			'Linked vulnerability cards should render the external lookup link with the expected attributes'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " actions-queue-group-card ")]',
			0,
			'Critical groups AJAX should not render the legacy group-card markup'
		);
	}

	public function test_detail_ajax_renders_selected_plugin_group_as_direct_investigation_table() :void {
		$this->seedCriticalAssetAndVulnerabilityQueue();
		$pluginSlug = self::con()->base_file;

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownDetail::SLUG, [
			'bucket' => 'critical',
			'group'  => 'plugins:'.$pluginSlug,
		] );
		$html = (string)( $payload[ 'html' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertStringEndsWith( ' - 1 item', (string)( $payload[ 'strip_text' ] ?? '' ) );
		$this->assertSame( '1 item', (string)( $payload[ 'strip_badge' ] ?? '' ) );
		$this->assertSame( 'plugins:'.$pluginSlug, (string)( $payload[ 'group_selection' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'direct_table', (string)( $payload[ 'group_selection' ][ 'detail_shell' ] ?? '' ) );
		$this->assertSame(
			[
				'path'      => [ 'Triage buckets', 'Fix now', 'Plugin Files', (string)( $payload[ 'group_selection' ][ 'label' ] ?? '' ) ],
				'focus'     => (string)( $payload[ 'context' ][ 'focus' ] ?? '' ),
				'next_step' => 'Review the selected asset table for the fastest next action.',
			],
			$payload[ 'context' ] ?? []
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-queue-detail="1"]',
			'Detail AJAX should wrap the selected group renderer in the drill-down detail container'
		);
		$this->assertInvestigationTableContractPresent(
			$xpath,
			'file_scan_results',
			'plugin',
			$pluginSlug,
			'Detail AJAX plugin drill'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-mode="actions_queue_assets" and @data-mode-shell="1"]',
			0,
			'Detail AJAX should render the shared flat table directly instead of reopening the asset-card chooser'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-shield-rail-target]',
			0,
			'Detail AJAX should not re-render the removed rail sidebar'
		);
	}

	public function test_plugin_detail_render_in_actions_queue_context_uses_asset_cards_shell() :void {
		$this->enablePremiumCapabilities( [
			'scan_pluginsthemes_local',
		] );

		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp', 'plugins' ] )
			 ->store();
		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
		$this->resetScanResultCountMemoization();

		$pluginSlug = self::con()->base_file;
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'      => 'plugin-file.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );

		$payload = $this->processActionPayloadWithAdminBypass( PluginsPane::SLUG, [
			'display_context' => 'actions_queue',
		] );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode="actions_queue_assets" and @data-mode-shell="1"]',
			'Plugin pane render in Actions Queue context should use the asset-card shell'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-tiles="1" and contains(concat(" ", normalize-space(@class), " "), " actions-queue-asset-cards__grid ")]',
			'Plugin pane render in Actions Queue context should render the asset-card grid'
		);
	}

	public function test_scans_assessment_rows_include_plugin_and_theme_files_only_when_asset_scan_gates_are_satisfied() :void {
		$this->requireController()->opts->optSet( 'file_scan_areas', [ 'wp', 'plugins', 'themes' ] );
		TestDataFactory::insertCompletedScan( 'afs', \time() - 7200 );

		$baselinePayload = $this->renderActionsQueueLandingPage();
		$this->assertRouteRenderOutputHealthy( $baselinePayload, 'actions queue landing asset scan checklist baseline' );
		$baselineScans = $this->findZoneTile(
			\is_array( $baselinePayload[ 'render_data' ][ 'vars' ][ 'zone_tiles' ] ?? null )
				? $baselinePayload[ 'render_data' ][ 'vars' ][ 'zone_tiles' ]
				: [],
			'scans'
		);
		$baselineKeys = \array_column( $baselineScans[ 'assessment_rows' ] ?? [], 'key' );
		$this->assertNotContains( 'plugin_files', $baselineKeys );
		$this->assertNotContains( 'theme_files', $baselineKeys );

		$this->enableAssetScanFixture( [ 'wp', 'plugins', 'themes' ] );

		$payload = $this->renderActionsQueueLandingPage();
		$this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing asset scan checklist gated' );
		$scans = $this->findZoneTile(
			\is_array( $payload[ 'render_data' ][ 'vars' ][ 'zone_tiles' ] ?? null )
				? $payload[ 'render_data' ][ 'vars' ][ 'zone_tiles' ]
				: [],
			'scans'
		);
		$assessmentKeys = \array_column( $scans[ 'assessment_rows' ] ?? [], 'key' );

		$this->assertContains( 'plugin_files', $assessmentKeys );
		$this->assertContains( 'theme_files', $assessmentKeys );
	}


	public function test_wordpress_pane_render_uses_core_investigation_file_status_table_contract() :void {
		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp' ] )
			 ->store();
		$this->resetScanResultCountMemoization();

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'    => 'wp-admin/admin.php',
			'is_in_core' => 1,
		] );

		$payload = $this->processActionPayloadWithAdminBypass( WordpressPane::SLUG, [
			'display_context' => 'actions_queue',
		] );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertInvestigationTableContractPresent(
			$xpath,
			'file_scan_results',
			'core',
			'core',
			'WordPress pane render'
		);
	}

	public function test_wordpress_pane_render_preserves_standard_no_results_message() :void {
		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp' ] )
			 ->store();
		$this->resetScanResultCountMemoization();

		TestDataFactory::insertCompletedScan( 'afs' );

		$payload = $this->processActionPayloadWithAdminBypass( WordpressPane::SLUG, [
			'display_context' => 'actions_queue',
		] );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertFlatEmptyStatePaneWithoutInvestigationTable( $xpath, 'WordPress pane render' );
	}

	public function test_plugin_pane_render_uses_investigation_file_status_table_contract() :void {
		$this->enablePremiumCapabilities( [
			'scan_pluginsthemes_local',
		] );

		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp', 'plugins' ] )
			 ->store();
		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
		$this->resetScanResultCountMemoization();

		$pluginSlug = self::con()->base_file;
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'      => 'plugin-file.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );

		$payload = $this->processActionPayloadWithAdminBypass( PluginsPane::SLUG );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-expand-trigger="1" and @data-shield-expand-target]',
			'Plugin pane render should keep the shared expandable summary row'
		);
		$this->assertInvestigationTableContractPresent(
			$xpath,
			'file_scan_results',
			'plugin',
			$pluginSlug,
			'Plugin pane render'
		);
	}

	public function test_theme_pane_render_uses_investigation_file_status_table_contract() :void {
		$this->enablePremiumCapabilities( [
			'scan_pluginsthemes_local',
		] );

		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp', 'themes' ] )
			 ->store();
		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
		$this->resetScanResultCountMemoization();

		$themeSlug = \wp_get_theme()->get_stylesheet();
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'     => 'theme-file.php',
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );

		$payload = $this->processActionPayloadWithAdminBypass( ThemesPane::SLUG );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-expand-trigger="1" and @data-shield-expand-target]',
			'Theme pane render should keep the shared expandable summary row'
		);
		$this->assertInvestigationTableContractPresent(
			$xpath,
			'file_scan_results',
			'theme',
			$themeSlug,
			'Theme pane render'
		);
	}

	public function test_plugin_pane_render_uses_disabled_callout_when_plugin_scanning_is_unavailable() :void {
		$payload = $this->processActionPayloadWithAdminBypass( PluginsPane::SLUG );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertDisabledPaneWithoutInvestigationTable( $xpath, 'Plugin pane render' );
	}

	public function test_malware_pane_render_uses_disabled_callout_when_malware_scanning_is_unavailable() :void {
		$payload = $this->processActionPayloadWithAdminBypass( MalwarePane::SLUG, [
			'display_context' => 'actions_queue',
		] );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertDisabledPaneWithoutInvestigationTable( $xpath, 'Malware pane render' );
	}

	public function test_malware_pane_render_uses_shared_investigation_table_contract_when_enabled() :void {
		$this->enablePremiumCapabilities( [
			'scan_malware_local',
		] );

		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp', 'malware_php' ] )
			 ->store();
		$this->resetScanResultCountMemoization();

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id' => 'infected.php',
			'is_mal'  => 1,
		] );

		$payload = $this->processActionPayloadWithAdminBypass( MalwarePane::SLUG, [
			'display_context' => 'actions_queue',
		] );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertInvestigationTableContractPresent(
			$xpath,
			'malware_scan_results',
			'malware',
			'malware',
			'Malware pane render'
		);
	}

	public function test_malware_pane_render_preserves_malware_empty_state_in_actions_queue() :void {
		$this->enablePremiumCapabilities( [
			'scan_malware_local',
		] );

		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp', 'malware_php' ] )
			 ->store();
		$this->resetScanResultCountMemoization();
		TestDataFactory::insertCompletedScan( 'afs' );

		$payload = $this->processActionPayloadWithAdminBypass( MalwarePane::SLUG, [
			'display_context' => 'actions_queue',
		] );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', \trim( $html ) );
		$this->assertFlatEmptyStatePaneWithoutInvestigationTable( $xpath, 'Malware pane render' );
	}

	public function test_scans_results_metrics_action_returns_exact_counts_for_enabled_tabs() :void {
		$this->enablePremiumCapabilities( [
			'scan_pluginsthemes_local',
			'scan_vulnerabilities',
			'scan_malware_local',
		] );

		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'enable_wpvuln_scan', 'Y' )
			 ->optSet( 'enabled_scan_apc', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp', 'plugins', 'themes', 'malware_php' ] )
			 ->store();
		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
		$this->resetScanResultCountMemoization();

		$pluginSlug = self::con()->base_file;
		$themeSlug = \wp_get_theme()->get_stylesheet();

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'      => 'wp-admin/admin.php',
			'is_in_core'   => 1,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'      => 'plugin-file.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'      => 'plugin-file-2.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'     => 'theme-file.php',
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id' => 'infected.php',
			'is_mal'  => 1,
		] );

		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultItem( $wpvId, [
			'item_id'       => $pluginSlug,
			'is_vulnerable' => 1,
		] );

		$apcId = TestDataFactory::insertCompletedScan( 'apc' );
		TestDataFactory::insertScanResultItem( $apcId, [
			'item_id'      => $themeSlug,
			'is_abandoned' => 1,
		] );

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueScanRailMetrics::SLUG );
		$tabs = \is_array( $payload[ 'tabs' ] ?? null ) ? $payload[ 'tabs' ] : [];
		$maintenance = $this->getMaintenanceQueueMetricsFromLanding();

		$this->assertSame( 1, (int)( $tabs[ 'wordpress' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'wordpress' ][ 'status' ] ?? '' ) );
		$this->assertSame( 1, (int)( $tabs[ 'plugins' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'plugins' ][ 'status' ] ?? '' ) );
		$this->assertSame( 1, (int)( $tabs[ 'themes' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'themes' ][ 'status' ] ?? '' ) );
		$this->assertSame( 2, (int)( $tabs[ 'vulnerabilities' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'vulnerabilities' ][ 'status' ] ?? '' ) );
		$this->assertSame( 1, (int)( $tabs[ 'malware' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'malware' ][ 'status' ] ?? '' ) );
		$this->assertSame( 0, (int)( $tabs[ 'file_locker' ][ 'count' ] ?? -1 ) );
		$this->assertSame( $maintenance[ 'count' ], (int)( $tabs[ 'maintenance' ][ 'count' ] ?? -1 ) );
		$this->assertSame( $maintenance[ 'status' ], (string)( $tabs[ 'maintenance' ][ 'status' ] ?? '' ) );
		$this->assertSame( 6 + $maintenance[ 'count' ], (int)( $tabs[ 'summary' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'summary' ][ 'status' ] ?? '' ) );
		$this->assertSame( 'critical', (string)( $payload[ 'rail_accent_status' ] ?? '' ) );
	}

	public function test_scans_results_metrics_action_returns_zero_neutral_entries_for_disabled_review_tabs_even_with_historical_results() :void {
		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp' ] )
			 ->store();
		$this->resetScanResultCountMemoization();

		$pluginSlug = self::con()->base_file;
		$themeSlug = \wp_get_theme()->get_stylesheet();

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'      => 'plugin-file.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'     => 'theme-file.php',
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id' => 'infected.php',
			'is_mal'  => 1,
		] );

		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultItem( $wpvId, [
			'item_id'       => $pluginSlug,
			'is_vulnerable' => 1,
		] );

		$apcId = TestDataFactory::insertCompletedScan( 'apc' );
		TestDataFactory::insertScanResultItem( $apcId, [
			'item_id'      => $themeSlug,
			'is_abandoned' => 1,
		] );
		$this->resetScanResultCountMemoization();

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueScanRailMetrics::SLUG );
		$tabs = \is_array( $payload[ 'tabs' ] ?? null ) ? $payload[ 'tabs' ] : [];
		$maintenance = $this->getMaintenanceQueueMetricsFromLanding();

		$this->assertSame( 0, (int)( $tabs[ 'wordpress' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 'good', (string)( $tabs[ 'wordpress' ][ 'status' ] ?? '' ) );
		$this->assertSame( 0, (int)( $tabs[ 'plugins' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 'neutral', (string)( $tabs[ 'plugins' ][ 'status' ] ?? '' ) );
		$this->assertSame( 0, (int)( $tabs[ 'themes' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 'neutral', (string)( $tabs[ 'themes' ][ 'status' ] ?? '' ) );
		$this->assertSame( 0, (int)( $tabs[ 'vulnerabilities' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 'neutral', (string)( $tabs[ 'vulnerabilities' ][ 'status' ] ?? '' ) );
		$this->assertSame( 0, (int)( $tabs[ 'malware' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 'neutral', (string)( $tabs[ 'malware' ][ 'status' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'file_locker', $tabs );
		$this->assertSame( $maintenance[ 'count' ], (int)( $tabs[ 'maintenance' ][ 'count' ] ?? -1 ) );
		$this->assertSame( $maintenance[ 'status' ], (string)( $tabs[ 'maintenance' ][ 'status' ] ?? '' ) );
		$this->assertSame( $maintenance[ 'count' ], (int)( $tabs[ 'summary' ][ 'count' ] ?? -1 ) );
		$this->assertSame( $maintenance[ 'status' ], (string)( $tabs[ 'summary' ][ 'status' ] ?? '' ) );
		$this->assertSame( $maintenance[ 'status' ], (string)( $payload[ 'rail_accent_status' ] ?? '' ) );
	}

	public function test_disabled_historical_scan_results_do_not_surface_in_actions_queue_summary() :void {
		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'N' )
			 ->optSet( 'file_scan_areas', [] )
			 ->store();

		$pluginSlug = self::con()->base_file;
		$themeSlug = \wp_get_theme()->get_stylesheet();

		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $scanId, [
			'item_id'    => 'wp-admin/admin.php',
			'is_in_core' => 1,
		] );
		TestDataFactory::insertScanResultItem( $scanId, [
			'item_id'      => 'plugin-file.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $scanId, [
			'item_id'     => 'theme-file.php',
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );
		$this->resetScanResultCountMemoization();

		$payload = $this->renderActionsQueueLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing disabled historical scan results' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertTrue( (bool)( $payload[ 'render_data' ][ 'flags' ][ 'queue_is_empty' ] ?? false ) );
		$this->assertXPathCount(
			$xpath,
			'//*[@data-drill-shell="1"]',
			0,
			'Historical results from disabled scans should not force the drill-down shell to render'
		);
	}

	public function test_scans_results_metrics_action_hides_file_locker_when_premium_unavailable() :void {
		$this->requireController()->opts
			 ->optSet( 'file_locker', [ 'wpconfig' ] )
			 ->store();

		$this->insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php', \time() );
		self::con()->comps->file_locker->clearLocks();

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueScanRailMetrics::SLUG );
		$tabs = \is_array( $payload[ 'tabs' ] ?? null ) ? $payload[ 'tabs' ] : [];
		$maintenance = $this->getMaintenanceQueueMetricsFromLanding();

		$this->assertArrayNotHasKey( 'file_locker', $tabs );
		$this->assertSame( $maintenance[ 'count' ], (int)( $tabs[ 'maintenance' ][ 'count' ] ?? -1 ) );
		$this->assertSame( $maintenance[ 'count' ], (int)( $tabs[ 'summary' ][ 'count' ] ?? 0 ) );
	}

	public function test_scans_results_metrics_action_counts_file_locker_when_enabled_and_problematic() :void {
		$this->enablePremiumCapabilities( [ 'scan_file_locker' ] );

		$this->requireController()->opts
			 ->optSet( 'file_locker', [ 'wpconfig' ] )
			 ->store();

		$this->insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php', \time() );
		self::con()->comps->file_locker->clearLocks();

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueScanRailMetrics::SLUG );
		$tabs = \is_array( $payload[ 'tabs' ] ?? null ) ? $payload[ 'tabs' ] : [];
		$maintenance = $this->getMaintenanceQueueMetricsFromLanding();

		$this->assertSame( 1, (int)( $tabs[ 'file_locker' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'warning', (string)( $tabs[ 'file_locker' ][ 'status' ] ?? '' ) );
		$this->assertSame( $maintenance[ 'count' ], (int)( $tabs[ 'maintenance' ][ 'count' ] ?? -1 ) );
		$this->assertSame( $maintenance[ 'count' ] + 1, (int)( $tabs[ 'summary' ][ 'count' ] ?? 0 ) );
		$this->assertSame(
			StatusPriority::highest( [ 'warning', $maintenance[ 'status' ] ], 'good' ),
			(string)( $tabs[ 'summary' ][ 'status' ] ?? '' )
		);
	}

	public function test_scans_results_metrics_action_dedupes_same_asset_across_vulnerable_and_abandoned_sections() :void {
		$this->enablePremiumCapabilities( [
			'scan_pluginsthemes_local',
			'scan_vulnerabilities',
		] );

		$this->requireController()->opts
			 ->optSet( 'enable_wpvuln_scan', 'Y' )
			 ->optSet( 'enabled_scan_apc', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'plugins' ] )
			 ->store();
		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
		$this->resetScanResultCountMemoization();

		$pluginSlug = self::con()->base_file;

		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultItem( $wpvId, [
			'item_id'       => $pluginSlug,
			'is_vulnerable' => 1,
		] );

		$apcId = TestDataFactory::insertCompletedScan( 'apc' );
		TestDataFactory::insertScanResultItem( $apcId, [
			'item_id'      => $pluginSlug,
			'is_abandoned' => 1,
		] );

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueScanRailMetrics::SLUG );
		$tabs = \is_array( $payload[ 'tabs' ] ?? null ) ? $payload[ 'tabs' ] : [];
		$maintenance = $this->getMaintenanceQueueMetricsFromLanding();

		$this->assertSame( 1, (int)( $tabs[ 'vulnerabilities' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $tabs[ 'vulnerabilities' ][ 'status' ] ?? '' ) );
		$this->assertSame( $maintenance[ 'count' ], (int)( $tabs[ 'maintenance' ][ 'count' ] ?? -1 ) );
		$this->assertSame( $maintenance[ 'count' ] + 1, (int)( $tabs[ 'summary' ][ 'count' ] ?? 0 ) );
		$this->assertSame(
			StatusPriority::highest( [ 'critical', $maintenance[ 'status' ] ], 'good' ),
			(string)( $tabs[ 'summary' ][ 'status' ] ?? '' )
		);
	}

	/**
	 * @return list<string>
	 */
	private function requireAtLeastInstalledPlugins( int $minimum ) :array {
		$pluginFiles = $this->getInstalledPluginFiles();

		if ( \count( $pluginFiles ) < $minimum ) {
			$this->markTestSkipped( 'Not enough installed plugins are available for this integration fixture.' );
		}

		return \array_slice( $pluginFiles, 0, $minimum );
	}

	/**
	 * @return list<string>
	 */
	private function getInstalledPluginFiles() :array {
		$pluginFiles = \array_values( \array_map(
			static fn( $file ) :string => (string)$file,
			\array_keys( Services::WpPlugins()->getPlugins() )
		) );
		\natsort( $pluginFiles );
		return \array_values( $pluginFiles );
	}

	/**
	 * @return list<string>
	 */
	private function requireAtLeastInactivePlugins( int $minimum ) :array {
		$inactivePlugins = \array_values( \array_diff(
			$this->getInstalledPluginFiles(),
			Services::WpPlugins()->getActivePlugins()
		) );

		if ( \count( $inactivePlugins ) < $minimum ) {
			$this->markTestSkipped( 'Not enough inactive plugins are available for this integration fixture.' );
		}

		return \array_slice( $inactivePlugins, 0, $minimum );
	}
}
