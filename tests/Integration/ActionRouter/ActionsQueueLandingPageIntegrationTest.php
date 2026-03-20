<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ActionsQueueScanRailMetrics;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MaintenanceItemIgnore;
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

	/**
	 * @param list<array{heading_label:string,groups:list<array<string,mixed>>}> $sections
	 * @return list<string>
	 */
	private function sectionGroupKeys( array $sections ) :array {
		$keys = [];
		foreach ( $sections as $section ) {
			foreach ( \is_array( $section[ 'groups' ] ?? null ) ? $section[ 'groups' ] : [] as $group ) {
				$keys[] = (string)( $group[ 'key' ] ?? '' );
			}
		}

		return $keys;
	}

	/**
	 * @param list<array{heading_label:string,groups:list<array<string,mixed>>}> $sections
	 * @return array<string,mixed>
	 */
	private function findGroupInSections( array $sections, string $groupKey ) :array {
		$matches = [];
		foreach ( $sections as $section ) {
			foreach ( \is_array( $section[ 'groups' ] ?? null ) ? $section[ 'groups' ] : [] as $group ) {
				if ( (string)( $group[ 'key' ] ?? '' ) === $groupKey ) {
					$matches[] = $group;
				}
			}
		}

		$this->assertCount( 1, $matches, 'Expected exactly one group for '.$groupKey );
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

	public function test_actions_queue_landing_renders_all_clear_with_drill_shell_when_queue_is_empty() :void {
		TestDataFactory::insertCompletedScan( 'afs', \time() - 7200 );

		$payload = $this->renderActionsQueueLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing baseline state' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$zoneTiles = \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [];

		$this->assertModeShellPayload( $vars, 'actions', 'critical', false );
		$this->assertModePanelPayload( $vars, '', false );
		$this->assertSame( 'All Clear', (string)( $vars[ 'mode_shell' ][ 'root_step' ][ 'badge' ] ?? '' ) );
		$this->assertIsString( $vars[ 'mode_shell' ][ 'root_step' ][ 'focus' ] ?? null );
		$this->assertCount( 2, $zoneTiles );
		$this->assertCount(
			2,
			\array_values( \array_filter( $zoneTiles, static fn( array $tile ) :bool => (bool)( $tile[ 'is_enabled' ] ?? false ) ) )
		);
		$this->assertNotEmpty( $this->findZoneTile( $zoneTiles, 'scans' )[ 'assessment_rows' ] ?? [] );
		$this->assertNotEmpty( $this->findZoneTile( $zoneTiles, 'maintenance' )[ 'assessment_rows' ] ?? [] );
		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'queue_is_empty' ] ?? false ) );
		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'has_drilldown_content' ] ?? false ) );
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
			1,
			'Empty actions queue should keep the drill-down shell when healthy bucket content exists'
		);
		$this->assertNotSame( '', \trim( $html ) );
	}

	public function test_actions_queue_landing_renders_drill_shell_and_bucket_cards_when_queue_has_items() :void {
		$this->setPluginUpdateAvailable();

		$payload = $this->renderActionsQueueLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'actions queue landing maintenance state' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$zoneTiles = \is_array( $vars[ 'zone_tiles' ] ?? null ) ? $vars[ 'zone_tiles' ] : [];
		$maintenance = $this->findZoneTile( $zoneTiles, 'maintenance' );
		$scans = $this->findZoneTile( $zoneTiles, 'scans' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertModeShellPayload( $vars, 'actions', 'critical', false );
		$this->assertModePanelPayload( $vars, '', false );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'queue_is_empty' ] ?? true ) );
		$this->assertSame( '1 item', (string)( $vars[ 'mode_shell' ][ 'root_step' ][ 'badge' ] ?? '' ) );
		$this->assertSame( '', (string)( $vars[ 'mode_shell' ][ 'root_step' ][ 'focus' ] ?? '' ) );
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
			'//*[@data-drill-layer-key="buckets" and string-length(@data-drill-layer-header) > 0]',
			'The shared drill shell should render PHP-prepared layer header JSON'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@data-drill-layer-key="buckets"]//*[@data-drill-layer-compact-back="1"]',
			0,
			'The migrated drill shell should remove the old compact back control markup'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-queue-section="drilldown"]/div[1][@data-drill-shell="1"]',
			'Actions queue should render the drill shell first for mobile-first stacking'
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
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " actions-queue-bucket-card__preview ")]',
			0,
			'Bucket layer should not render the removed bucket preview row'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-healthy-disclosure-toggle="1"]',
			'Bucket layer should render the shared healthy disclosure toggle when healthy assessment rows exist'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-healthy-disclosure-toggle="1" and @aria-expanded="false"]',
			'Bucket layer should render the healthy disclosure toggle with the closed accessibility state'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-healthy-disclosure-toggle="1"][contains(normalize-space(), "No action required")]',
			'Bucket layer should label the healthy summary section'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-healthy-disclosure-body="1" and @aria-hidden="true"]',
			'Bucket layer should render the healthy disclosure body with the closed accessibility state'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-healthy-disclosure-body="1"]//*[contains(concat(" ", normalize-space(@class), " "), " item-box--good ")]',
			'Bucket layer should render the healthy summary content inside the disclosure body'
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

		$this->assertSame( 'Review next', (string)( $payload[ 'header' ][ 'title' ] ?? '' ) );
		$this->assertSame( '1 item', (string)( $payload[ 'header' ][ 'badge' ] ?? '' ) );
		$this->assertSame( 'warning', (string)( $payload[ 'header' ][ 'badge_status' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'landing_refresh', $payload );
		$this->assertSame( 'review', (string)( $payload[ 'bucket_selection' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'Review next', (string)( $payload[ 'bucket_selection' ][ 'label' ] ?? '' ) );
		$this->assertSame( 'Back to Actions Queue', (string)( $payload[ 'header' ][ 'active_back_label' ] ?? '' ) );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-actions-queue-groups="1"]',
			'Groups AJAX should render the selected bucket wrapper'
		);
		$this->assertSame( [ 'wp_plugins_updates' ], $this->sectionGroupKeys( $payload[ 'active_sections' ] ?? [] ) );
		$activeGroup = $this->findGroupInSections( $payload[ 'active_sections' ] ?? [], 'wp_plugins_updates' );
		$this->assertSame( 'category', (string)( $activeGroup[ 'card_type' ] ?? '' ) );
		$this->assertNotEmpty( $activeGroup[ 'management_link' ] ?? [] );
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
		$this->assertNotEmpty( $activeGroup[ 'maintenance_rows' ] ?? [] );
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " item-box__row-summary ")]',
			0,
			'Groups AJAX should remove the grouped maintenance row context clutter'
		);
		$this->assertXPathExists(
			$xpath,
			'//a[contains(concat(" ", normalize-space(@class), " "), " item-box__row-action ") and string-length(@data-actions-queue-maintenance-action) > 0]',
			'Groups AJAX should render the existing inline maintenance ignore action payload on category rows'
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

	public function test_groups_ajax_keeps_fully_ignored_review_group_visible_in_looking_good_section() :void {
		$this->setPluginUpdateAvailable();

		$response = $this->processMaintenanceAction( MaintenanceItemIgnore::SLUG, [
			'maintenance_key' => 'wp_plugins_updates',
			'identifier'      => self::con()->base_file,
		] );
		$this->assertTrue( (bool)( $response[ 'success' ] ?? false ) );

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket' => 'review',
		] );
		$html = (string)( $payload[ 'html' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame( 'Review next', (string)( $payload[ 'header' ][ 'title' ] ?? '' ) );
		$this->assertSame( '0 items', (string)( $payload[ 'header' ][ 'badge' ] ?? '' ) );
		$this->assertSame( [ 'wp_plugins_updates' ], $this->sectionGroupKeys( $payload[ 'healthy_sections' ] ?? [] ) );
		$healthyGroup = $this->findGroupInSections( $payload[ 'healthy_sections' ] ?? [], 'wp_plugins_updates' );
		$this->assertSame( 'No action required', (string)( $payload[ 'healthy_heading_label' ] ?? '' ) );
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " finding-group__heading ") and normalize-space()="Looking good"]',
			0,
			'Review groups AJAX should not render the removed healthy section heading'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-healthy-disclosure-toggle="1"][contains(normalize-space(), "No action required")]',
			'Review groups AJAX should render the shared healthy disclosure toggle'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-healthy-disclosure-toggle="1" and @aria-expanded="false"]',
			'Review groups AJAX should render the healthy disclosure toggle with the closed accessibility state'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-healthy-disclosure-body="1" and @aria-hidden="true"]',
			'Review groups AJAX should render the healthy disclosure body with the closed accessibility state'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-healthy-disclosure-body="1"]//*[contains(concat(" ", normalize-space(@class), " "), " item-box--good ")]',
			'Review groups AJAX should render healthy maintenance groups inside the disclosure body'
		);
		$this->assertXPathExists(
			$xpath,
			'//a[contains(concat(" ", normalize-space(@class), " "), " item-box__row-action ") and contains(@data-actions-queue-maintenance-action, "maintenance_item_unignore")]',
			'Review groups AJAX should keep the unignore action available on healthy ignored maintenance rows'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " actions-landing__empty-state ")]',
			0,
			'Healthy review groups should render instead of the generic empty-state message'
		);
		$this->assertSame( 'category', (string)( $healthyGroup[ 'card_type' ] ?? '' ) );
	}

	public function test_groups_ajax_can_refresh_the_current_selected_group_summary() :void {
		$this->setPluginUpdateAvailable();
		$initialPayload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket' => 'review',
		] );
		$selectedGroupKey = (string)( $this->sectionGroupKeys( $initialPayload[ 'active_sections' ] ?? [] )[ 0 ] ?? '' );
		$this->assertNotSame( '', $selectedGroupKey );

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket'                  => 'review',
			'group'                   => $selectedGroupKey,
			'include_landing_refresh' => 1,
		] );

		$this->assertSame( $selectedGroupKey, (string)( $payload[ 'selected_group' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'maintenance', (string)( $payload[ 'selected_group' ][ 'detail_shell' ] ?? '' ) );
		$this->assertSame(
			(string)( $payload[ 'selected_group' ][ 'label' ] ?? '' ),
			(string)( $payload[ 'selected_group' ][ 'header' ][ 'title' ] ?? '' )
		);
		$this->assertSame( '1 item', (string)( $payload[ 'selected_group' ][ 'header' ][ 'badge' ] ?? '' ) );
		$this->assertSame( 1, (int)( $payload[ 'selected_group' ][ 'item_count' ] ?? 0 ) );
		$this->assertFalse( (bool)( $payload[ 'landing_refresh' ][ 'queue_is_empty' ] ?? true ) );
		$this->assertTrue( (bool)( $payload[ 'landing_refresh' ][ 'has_drilldown_content' ] ?? false ) );
		$this->assertNotSame( '', (string)( $payload[ 'landing_refresh' ][ 'root_step_json' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $payload[ 'landing_refresh' ][ 'buckets_html' ] ?? '' ) );
		$this->assertSame( 'review', (string)( $payload[ 'bucket_selection' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'Back to Review next', (string)( $payload[ 'selected_group' ][ 'header' ][ 'active_back_label' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $payload[ 'selected_group' ][ 'header' ][ 'summary' ] ?? '' ) );
	}

	public function test_groups_ajax_keeps_healthy_vulnerabilities_group_drillable_and_detail_renderable_in_critical_bucket() :void {
		$this->enableAssetScanFixture( [ 'plugins' ] );

		TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertCompletedScan( 'apc' );
		$this->resetScanResultCountMemoization();

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket' => 'critical',
		] );
		$html = (string)( $payload[ 'html' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );
		$vulnerabilities = $this->findGroupInSections( $payload[ 'healthy_sections' ] ?? [], 'vulnerabilities' );
		$this->assertSame( 'linked', (string)( $vulnerabilities[ 'card_type' ] ?? '' ) );
		$this->assertFalse( (bool)( $vulnerabilities[ 'is_interactive' ] ?? true ) );
		$this->assertXPathCount(
			$xpath,
			"//button[contains(concat(\" \", normalize-space(@class), \" \"), \" finding-card--expandable \") and @data-drill-target=\"detail\" and contains(@data-drill-group-selection, '\"key\":\"vulnerabilities\"')]",
			0,
			'Healthy vulnerabilities critical card should no longer be drillable when there are no results to show'
		);
	}

	public function test_groups_ajax_renders_finding_cards_for_critical_bucket_groups() :void {
		$this->seedCriticalAssetAndVulnerabilityQueue();

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket' => 'critical',
		] );
		$html = (string)( $payload[ 'html' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame( 'Fix now', (string)( $payload[ 'header' ][ 'title' ] ?? '' ) );
		$this->assertSame( '3 items', (string)( $payload[ 'header' ][ 'badge' ] ?? '' ) );
		$this->assertSame( 'critical', (string)( $payload[ 'header' ][ 'badge_status' ] ?? '' ) );
		$this->assertSame( 'critical', (string)( $payload[ 'bucket_selection' ][ 'status' ] ?? '' ) );
		$this->assertSame( [ 'Known Vulnerabilities', 'Plugin Files', 'Theme Files' ], \array_column( $payload[ 'active_sections' ] ?? [], 'heading_label' ) );
		$this->assertStringStartsWith( 'vulnerabilities:', (string)( $payload[ 'active_sections' ][ 0 ][ 'groups' ][ 0 ][ 'key' ] ?? '' ) );
		$this->assertSame( 'linked', (string)( $payload[ 'active_sections' ][ 0 ][ 'groups' ][ 0 ][ 'card_type' ] ?? '' ) );
		$this->assertSame( '', (string)( $payload[ 'active_sections' ][ 0 ][ 'groups' ][ 0 ][ 'drill_hint' ] ?? '' ) );
		$this->assertSame( 'plugins:'.self::con()->base_file, (string)( $payload[ 'active_sections' ][ 1 ][ 'groups' ][ 0 ][ 'key' ] ?? '' ) );
		$this->assertSame( 'expandable', (string)( $payload[ 'active_sections' ][ 1 ][ 'groups' ][ 0 ][ 'card_type' ] ?? '' ) );
		$this->assertSame( 'View 1 files', (string)( $payload[ 'active_sections' ][ 1 ][ 'groups' ][ 0 ][ 'drill_hint' ] ?? '' ) );
		$this->assertSame( 'themes:'.\wp_get_theme()->get_stylesheet(), (string)( $payload[ 'active_sections' ][ 2 ][ 'groups' ][ 0 ][ 'key' ] ?? '' ) );
		$this->assertSame( 'expandable', (string)( $payload[ 'active_sections' ][ 2 ][ 'groups' ][ 0 ][ 'card_type' ] ?? '' ) );
		$this->assertSame( 'View 1 files', (string)( $payload[ 'active_sections' ][ 2 ][ 'groups' ][ 0 ][ 'drill_hint' ] ?? '' ) );
		$this->assertCount( 2, $payload[ 'active_sections' ][ 0 ][ 'groups' ][ 0 ][ 'links' ] ?? [] );
		$this->assertSame( '/wp-admin/plugins.php', (string)( $payload[ 'active_sections' ][ 0 ][ 'groups' ][ 0 ][ 'links' ][ 0 ][ 'href' ] ?? '' ) );
		$this->assertSame(
			'https://clk.shldscrty.com/shieldvulnerabilitylookup?type=plugin&slug='.self::con()->base_file.'&version='.self::con()->cfg->version(),
			(string)( $payload[ 'active_sections' ][ 0 ][ 'groups' ][ 0 ][ 'links' ][ 1 ][ 'href' ] ?? '' )
		);
		$this->assertSame( '_blank', (string)( $payload[ 'active_sections' ][ 0 ][ 'groups' ][ 0 ][ 'links' ][ 1 ][ 'target' ] ?? '' ) );
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

	public function test_groups_ajax_routes_abandoned_only_findings_to_fix_now() :void {
		$this->enableAssetScanFixture( [ 'plugins' ] );

		$pluginSlug = self::con()->base_file;
		$apcId = TestDataFactory::insertCompletedScan( 'apc' );
		TestDataFactory::insertScanResultItem( $apcId, [
			'item_id'      => $pluginSlug,
			'is_abandoned' => 1,
		] );
		$this->resetScanResultCountMemoization();

		$payload = $this->processActionPayloadWithAdminBypass( ActionsQueueDrillDownGroups::SLUG, [
			'bucket' => 'critical',
		] );

		$this->assertSame( 'Fix now', (string)( $payload[ 'header' ][ 'title' ] ?? '' ) );
		$this->assertSame( 'critical', (string)( $payload[ 'bucket_selection' ][ 'status' ] ?? '' ) );
		$this->assertSame( [ 'vulnerabilities:abandoned-'.$pluginSlug ], $this->sectionGroupKeys( $payload[ 'active_sections' ] ?? [] ) );
		$this->assertSame( 'Abandoned Assets', (string)( $payload[ 'active_sections' ][ 0 ][ 'heading_label' ] ?? '' ) );
		$this->assertSame( 'critical', (string)( $payload[ 'active_sections' ][ 0 ][ 'groups' ][ 0 ][ 'status' ] ?? '' ) );
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

		$this->assertSame( '1 item', (string)( $payload[ 'header' ][ 'badge' ] ?? '' ) );
		$this->assertSame( 'plugins:'.$pluginSlug, (string)( $payload[ 'group_selection' ][ 'key' ] ?? '' ) );
		$this->assertSame( 'direct_table', (string)( $payload[ 'group_selection' ][ 'detail_shell' ] ?? '' ) );
		$this->assertSame( (string)( $payload[ 'group_selection' ][ 'label' ] ?? '' ), (string)( $payload[ 'header' ][ 'title' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $payload[ 'header' ][ 'summary' ] ?? '' ) );
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
		$this->assertTrue( (bool)( $payload[ 'render_data' ][ 'flags' ][ 'has_drilldown_content' ] ?? false ) );
		$this->assertXPathCount(
			$xpath,
			'//*[@data-drill-shell="1"]',
			1,
			'Historical results from disabled scans should still allow the healthy drill-down shell to render'
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

	private function processMaintenanceAction( string $slug, array $data ) :array {
		return ( new ActionProcessor() )->processAction( $slug, $data )->payload();
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
