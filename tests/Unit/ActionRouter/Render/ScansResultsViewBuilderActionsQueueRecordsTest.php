<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueAssetMetadataResolver,
	ActionsQueueScanAssetCardsBuilder,
	ScansResultsViewBuilder
};

class ScansResultsViewBuilderActionsQueueRecordsTest extends ScansResultsViewBuilderTestCase {

	public function test_actions_queue_plugin_pane_preserves_ignored_only_contract_in_real_asset_card_tables() :void {
		$assetMetadataResolver = new class extends ActionsQueueAssetMetadataResolver {

			public function resolve( string $assetType, string $assetKey ) :?array {
				return [
					'subject_type' => 'plugin',
					'subject_id'   => 'example-plugin/example-plugin.php',
					'title'        => 'Example Plugin',
					'icon_class'   => 'bi bi-plug-fill',
					'has_update'   => false,
				];
			}
		};

		$assetCardsBuilder = new class( $assetMetadataResolver ) extends ActionsQueueScanAssetCardsBuilder {

			private array $seenOptions = [];

			public function __construct( ActionsQueueAssetMetadataResolver $assetMetadataResolver ) {
				parent::__construct( $assetMetadataResolver );
			}

			protected function retrieveGroupedAssetSummaries( string $assetType, array $resultsDisplayOptions ) :array {
				$this->seenOptions[] = $resultsDisplayOptions;
				return [
					[ 'slug' => 'example-plugin', 'file_count' => 2 ],
				];
			}

			protected function buildFullLogHref() :string {
				return '/queue/scans';
			}

			public function getSeenOptions() :array {
				return $this->seenOptions;
			}
		};

		$builder = new class( $assetCardsBuilder ) extends ScansResultsViewBuilder {

			/** @var ActionsQueueScanAssetCardsBuilder */
			private $assetCardsBuilder;

			public function __construct( ActionsQueueScanAssetCardsBuilder $assetCardsBuilder ) {
				$this->assetCardsBuilder = $assetCardsBuilder;
			}

			protected function getRailTabAvailability( string $tabKey ) :array {
				return [
					'is_available'          => true,
					'show_in_actions_queue' => true,
					'disabled_message'      => '',
					'disabled_status'       => 'neutral',
				];
			}

			protected function buildActionsQueueAssetCardsBuilder() :ActionsQueueScanAssetCardsBuilder {
				return $this->assetCardsBuilder;
			}
		};

		$pane = $builder->buildActionsQueuePluginsPane( [
			'include_ignored' => true,
			'ignored_only'    => true,
		] );

		$this->assertFalse( $pane[ 'is_disabled' ] );
		$this->assertSame(
			[
				[
					'include_ignored'  => true,
					'include_repaired' => false,
					'include_deleted'  => false,
					'ignored_only'     => true,
				],
			],
			$assetCardsBuilder->getSeenOptions()
		);
		$this->assertSame( '2 ignored files are available for review', $pane[ 'cards' ][ 0 ][ 'stat_text' ] );
		$tableAction = $this->decodeJsonAttr( (string)( $pane[ 'cards' ][ 0 ][ 'table' ][ 'table_action_attr' ] ?? '' ) );
		$this->assertSame( 'plugin', $tableAction[ 'type' ] ?? '' );
		$this->assertSame( 'example-plugin/example-plugin.php', $tableAction[ 'file' ] ?? '' );
		$this->assertSame( 'actions_queue', $tableAction[ 'display_context' ] ?? '' );
		$this->assertSame(
			[
				'include_ignored'  => true,
				'include_repaired' => false,
				'include_deleted'  => false,
				'ignored_only'     => true,
			],
			$tableAction[ 'results_display_options' ] ?? []
		);
	}

	public function test_actions_queue_plugin_pane_reuses_shared_issue_records_for_cards_and_rail_rows() :void {
		$builder = $this->createBuilder( [
			'pluginsEnabled'     => true,
			'pluginIssueRecords' => [
				$this->makePluginThemeIssueRecord( 'plugin', 'example-plugin', 'Example Plugin', 'example-plugin/example-plugin.php', 3 ),
			],
		] );

		$queuePane = $builder->buildActionsQueuePluginsPane();
		$railPane = $builder->buildRailPaneData( 'plugins' );

		$this->assertFalse( $queuePane[ 'is_disabled' ] );
		$this->assertCount( 1, $queuePane[ 'cards' ] );
		$this->assertSame( 'example-plugin', $queuePane[ 'cards' ][ 0 ][ 'key' ] );
		$this->assertSame( 'actions-queue-plugin-example-plugin', $queuePane[ 'cards' ][ 0 ][ 'panel_target' ] );
		$this->assertSame( 'example-plugin/example-plugin.php', $queuePane[ 'cards' ][ 0 ][ 'meta_text' ] );
		$this->assertTrue( $queuePane[ 'cards' ][ 0 ][ 'show_meta_in_tile' ] );
		$this->assertSame( 3, $queuePane[ 'cards' ][ 0 ][ 'count_badge' ] );
		$this->assertSame( '/wp-admin/plugins.php', $queuePane[ 'cards' ][ 0 ][ 'actions' ][ 0 ][ 'href' ] );
		$this->assertSame( 'bi bi-power', $queuePane[ 'cards' ][ 0 ][ 'actions' ][ 0 ][ 'icon_class' ] );
		$this->assertSame( 'Go to plugins', $queuePane[ 'cards' ][ 0 ][ 'actions' ][ 0 ][ 'tooltip_attr' ] );
		$this->assertSame( '1', $queuePane[ 'cards' ][ 0 ][ 'panel_data' ][ 'actions-queue-asset-panel-loaded' ] ?? '' );
		$this->assertSame( '0', $queuePane[ 'cards' ][ 0 ][ 'panel_data' ][ 'actions-queue-asset-panel-lazy' ] ?? '' );
		$queueTableAction = $this->decodeJsonAttr( (string)( $queuePane[ 'cards' ][ 0 ][ 'table' ][ 'table_action_attr' ] ?? '' ) );
		$railTableAction = $this->decodeJsonAttr( (string)( $railPane[ 'items' ][ 0 ][ 'expansion' ][ 'table' ][ 'table_action_attr' ] ?? '' ) );
		$this->assertSame( 'plugin', $queueTableAction[ 'type' ] ?? '' );
		$this->assertSame( 'example-plugin/example-plugin.php', $queueTableAction[ 'file' ] ?? '' );
		$this->assertCount( 1, $railPane[ 'items' ] );
		$this->assertSame( 'scan-files-plugin-example-plugin', $railPane[ 'items' ][ 0 ][ 'expand_target' ] );
		$this->assertSame( $queueTableAction[ 'file' ] ?? '', $railTableAction[ 'file' ] ?? '' );
	}

	private function decodeJsonAttr( string $json ) :array {
		return $json === '' ? [] : \json_decode( $json, true, 512, \JSON_THROW_ON_ERROR );
	}

	public function test_actions_queue_theme_pane_returns_disabled_state_when_scan_is_unavailable() :void {
		$message = 'themes-queue-unavailable-sentinel';
		$builder = $this->createBuilder( [
			'themesEnabled' => false,
			'tabAvailability' => [
				'themes' => [
					'is_available'          => false,
					'show_in_actions_queue' => true,
					'disabled_message'      => $message,
					'disabled_status'       => 'neutral',
				],
			],
		] );

		$pane = $builder->buildActionsQueueThemesPane();
		$this->assertTrue( $pane[ 'is_disabled' ] );
		$this->assertSame( $message, $pane[ 'disabled_message' ] );
		$this->assertSame( [], $pane[ 'cards' ] );
	}

	public function test_actions_queue_file_locker_pane_uses_per_card_panels_and_orders_problem_pending_and_good_locks() :void {
		$builder = $this->createBuilder( [
			'problemFileLocks' => [ (object)[ 'id' => 14, 'path' => '/wp-config.php', 'detected_at' => 1000, 'hash_current' => '' ] ],
			'pendingFileLockDisplays' => [
				[
					'file_key' => 'root_index',
					'title'    => 'index.php',
					'path'     => '/index.php',
				],
			],
			'goodFileLocks'    => [ (object)[ 'id' => 15, 'path' => '/.htaccess', 'detected_at' => 0, 'hash_current' => 'abc123' ] ],
		] );

		$pane = $builder->buildActionsQueueFileLockerPane();
		$this->assertFalse( $pane[ 'is_disabled' ] );
		$this->assertCount( 3, $pane[ 'cards' ] );
		$this->assertSame( 'warning', $pane[ 'cards' ][ 0 ][ 'status' ] );
		$this->assertSame( 'neutral', $pane[ 'cards' ][ 1 ][ 'status' ] );
		$this->assertSame( 'good', $pane[ 'cards' ][ 2 ][ 'status' ] );
		$this->assertSame( 'actions-queue-filelocker-card-14', $pane[ 'cards' ][ 0 ][ 'panel_id' ] );
		$this->assertSame( 'actions-queue-filelocker-14', $pane[ 'cards' ][ 0 ][ 'panel_target' ] );
		$this->assertSame( '/wp-config.php', $pane[ 'cards' ][ 0 ][ 'meta_text' ] );
		$this->assertFalse( $pane[ 'cards' ][ 0 ][ 'show_meta_in_tile' ] );
		$this->assertSame( '0', $pane[ 'cards' ][ 0 ][ 'panel_data' ][ 'actions-queue-asset-panel-loaded' ] ?? '' );
		$this->assertSame( '1', $pane[ 'cards' ][ 0 ][ 'panel_data' ][ 'actions-queue-asset-panel-lazy' ] ?? '' );
		$this->assertSame(
			[
				'render_slug' => 'filelocker_showdiff',
				'rid'         => 14,
			],
			\json_decode( (string)( $pane[ 'cards' ][ 0 ][ 'panel_data' ][ 'actions-queue-asset-render-action' ] ?? '' ), true )
		);
		$this->assertSame( '1', $pane[ 'cards' ][ 1 ][ 'panel_data' ][ 'actions-queue-asset-panel-loaded' ] ?? '' );
		$this->assertSame( '0', $pane[ 'cards' ][ 1 ][ 'panel_data' ][ 'actions-queue-asset-panel-lazy' ] ?? '' );
		$this->assertSame( [], \json_decode( (string)( $pane[ 'cards' ][ 1 ][ 'panel_data' ][ 'actions-queue-asset-render-action' ] ?? '[]' ), true ) );
		$this->assertSame( 'Initial lock is still being created.', $pane[ 'cards' ][ 1 ][ 'stat_text' ] );
		$this->assertSame( '/index.php', $pane[ 'cards' ][ 1 ][ 'meta_text' ] );
		$this->assertNotSame( '', $pane[ 'cards' ][ 1 ][ 'body_notice' ] );
		$this->assertSame( 'info', $pane[ 'cards' ][ 1 ][ 'body_notice_variant' ] );
	}

	public function test_file_locker_groups_problem_pending_and_good_locks_into_distinct_sections_without_changing_issue_count() :void {
		$builder = $this->createBuilder( [
			'fileLockerPayload' => $this->buildFileLockerPayload( '', true ),
			'problemFileLocks'  => [ (object)[ 'id' => 21, 'path' => '/wp-config.php', 'detected_at' => 1000, 'hash_current' => '' ] ],
			'pendingFileLockDisplays' => [
				[
					'file_key' => 'root_index',
					'title'    => 'index.php',
					'path'     => '/index.php',
				],
			],
			'goodFileLocks'     => [ (object)[ 'id' => 22, 'path' => '/.htaccess', 'detected_at' => 0, 'hash_current' => 'abc123' ] ],
		] );

		$flTab = $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'file_locker' );
		$items = $flTab[ 'items' ] ?? [];

		$this->assertCount( 3, $items );
		$this->assertSame( 'warning', $items[ 0 ][ 'status' ] ?? '' );
		$this->assertSame( 'neutral', $items[ 1 ][ 'status' ] ?? '' );
		$this->assertSame( 'good', $items[ 2 ][ 'status' ] ?? '' );
		$this->assertNotSame( '', $items[ 0 ][ 'section_label' ] ?? '' );
		$this->assertNotSame( '', $items[ 1 ][ 'section_label' ] ?? '' );
		$this->assertNotSame( $items[ 0 ][ 'section_label' ] ?? '', $items[ 1 ][ 'section_label' ] ?? '' );
		$this->assertSame( 'Pending', $items[ 1 ][ 'section_label' ] ?? '' );
		$this->assertSame( 1, $flTab[ 'count' ] ?? -1 );
		$this->assertSame( 'warning', $flTab[ 'status' ] ?? '' );
	}

	public function test_file_locker_returns_empty_when_disabled() :void {
		$builder = $this->createBuilder( [
			'fileLockerPayload' => $this->buildFileLockerPayload( '', false ),
			'problemFileLocks'  => [ (object)[ 'path' => '/test', 'detected_at' => 1, 'hash_current' => '' ] ],
		] );

		$flTab = $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'file_locker' );
		$this->assertEmpty( $flTab[ 'items' ] ?? [] );
		$this->assertSame( 'good', $flTab[ 'status' ] ?? '' );
	}

	public function test_vulnerability_items_preserve_incoming_section_group_labels() :void {
		$vulnerableLabel = 'vulnerable-section-sentinel';
		$abandonedLabel = 'abandoned-section-sentinel';
		$builder = $this->createBuilder( [
			'vulnerabilitiesEnabled' => true,
			'vulnerabilities'        => [
				'count'    => 2,
				'status'   => 'critical',
				'sections' => [
					'vulnerable' => [
						'label' => $vulnerableLabel,
						'items' => [
							[ 'label' => 'Vuln 1', 'description' => 'Desc', 'severity' => 'critical', 'count' => 1, 'actions' => [] ],
						],
					],
					'abandoned' => [
						'label' => $abandonedLabel,
						'items' => [
							[ 'label' => 'Old Plugin', 'description' => 'Desc', 'severity' => 'critical', 'count' => 1, 'actions' => [] ],
						],
					],
				],
			],
		] );

		$sectionLabels = \array_column(
			$this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'vulnerabilities' )[ 'items' ] ?? [],
			'section_label'
		);
		$this->assertContains( $vulnerableLabel, $sectionLabels );
		$this->assertContains( $abandonedLabel, $sectionLabels );
	}

	public function test_vulnerability_items_keep_native_and_lookup_actions() :void {
		$builder = $this->createBuilder( [
			'vulnerabilitiesEnabled' => true,
			'vulnerabilities'        => [
				'count'    => 1,
				'status'   => 'critical',
				'sections' => [
					'vulnerable' => [
						'label' => 'Known Vulnerabilities',
						'items' => [
							[
								'label'       => 'Vuln 1',
								'description' => 'Desc',
								'severity'    => 'critical',
								'count'       => 1,
								'actions'     => [
									[
										'href'  => '/wp-admin/update-core.php',
										'label' => 'Go to updates',
										'type'  => 'update',
									],
									[
										'href'       => 'https://lookup.example/plugin',
										'label'      => 'Vulnerability Lookup',
										'type'       => 'navigate',
										'attributes' => [
											'target' => '_blank',
										],
									],
								],
							],
						],
					],
				],
			],
		] );

		$actions = $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'vulnerabilities' )[ 'items' ][ 0 ][ 'actions' ] ?? [];
		$this->assertCount( 2, $actions );
		$this->assertSame( 'update', $actions[ 0 ][ 'type' ] ?? '' );
		$this->assertSame( '/wp-admin/update-core.php', $actions[ 0 ][ 'href' ] ?? '' );
		$this->assertSame( 'navigate', $actions[ 1 ][ 'type' ] ?? '' );
		$this->assertSame( 'https://lookup.example/plugin', $actions[ 1 ][ 'href' ] ?? '' );
		$this->assertSame( '_blank', $actions[ 1 ][ 'attributes' ][ 'target' ] ?? '' );
	}

	public function test_summary_items_with_known_keys_get_row_level_rail_switch_attributes() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled'       => true,
			'pluginsEnabled'         => true,
			'themesEnabled'          => true,
			'vulnerabilitiesEnabled' => true,
			'malwareEnabled'         => true,
			'summaryRows'            => [
				[ 'key' => 'wp_files', 'label' => 'WordPress Files', 'text' => 'Issues', 'severity' => 'critical', 'count' => 2 ],
				[ 'key' => 'plugin_files', 'label' => 'Plugin Files', 'text' => 'Issues', 'severity' => 'warning', 'count' => 1 ],
				[ 'key' => 'theme_files', 'label' => 'Theme Files', 'text' => 'Issues', 'severity' => 'warning', 'count' => 1 ],
				[ 'key' => 'malware', 'label' => 'Malware', 'text' => 'Issues', 'severity' => 'critical', 'count' => 1 ],
				[ 'key' => 'vulnerable_assets', 'label' => 'Vulns', 'text' => 'Issues', 'severity' => 'critical', 'count' => 3 ],
				[ 'key' => 'abandoned', 'label' => 'Abandoned', 'text' => 'Issues', 'severity' => 'critical', 'count' => 1 ],
				[ 'key' => 'file_locker', 'label' => 'File Locker', 'text' => 'Issues', 'severity' => 'warning', 'count' => 1 ],
			],
		] );

		$items = $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'summary' )[ 'items' ] ?? [];
		$this->assertCount( 7, $items );
		$this->assertCount( 1, \array_unique( \array_column( $items, 'section_label' ) ) );
		$this->assertNotSame( '', $items[ 0 ][ 'section_label' ] ?? '' );
		foreach ( $items as $item ) {
			$this->assertSame( [], $item[ 'actions' ] ?? [] );
			$this->assertNotEmpty( $item[ 'attributes' ][ 'data-shield-rail-switch' ] ?? '' );
			$this->assertSame( 'button', $item[ 'attributes' ][ 'role' ] ?? '' );
		}
	}

	public function test_summary_row_switch_attributes_map_to_correct_tabs() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled'       => true,
			'pluginsEnabled'         => true,
			'vulnerabilitiesEnabled' => true,
			'summaryRows'            => [
				[ 'key' => 'wp_files', 'label' => 'WordPress Files', 'text' => 'Issues', 'severity' => 'critical', 'count' => 2 ],
				[ 'key' => 'plugin_files', 'label' => 'Plugin Files', 'text' => 'Issues', 'severity' => 'warning', 'count' => 1 ],
				[ 'key' => 'vulnerable_assets', 'label' => 'Vulns', 'text' => 'Issues', 'severity' => 'critical', 'count' => 3 ],
				[ 'key' => 'abandoned', 'label' => 'Abandoned', 'text' => 'Issues', 'severity' => 'critical', 'count' => 1 ],
				[ 'key' => 'file_locker', 'label' => 'File Locker', 'text' => 'Issues', 'severity' => 'warning', 'count' => 1 ],
			],
		] );

		$targets = [];
		foreach ( $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'summary' )[ 'items' ] ?? [] as $item ) {
			if ( isset( $item[ 'attributes' ][ 'data-shield-rail-switch' ] ) ) {
				$targets[] = $item[ 'attributes' ][ 'data-shield-rail-switch' ];
			}
		}

		$this->assertContains( 'wordpress', $targets );
		$this->assertContains( 'plugins', $targets );
		$this->assertContains( 'file_locker', $targets );
		$this->assertSame( 2, \count( \array_filter( $targets, static fn( string $target ) :bool => $target === 'vulnerabilities' ) ) );
	}

	public function test_summary_items_with_unknown_keys_fallback_to_href_actions_without_switch_attributes() :void {
		$builder = $this->createBuilder( [
			'summaryRows' => [
				[
					'key'      => 'wp_updates',
					'label'    => 'WP Updates',
					'text'     => 'Update available',
					'severity' => 'warning',
					'count'    => 1,
					'action'   => 'Update',
					'href'     => 'https://example.com/update',
				],
			],
		] );

		$items = $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'summary' )[ 'items' ] ?? [];
		$this->assertCount( 1, $items );
		$this->assertNotSame( '', $items[ 0 ][ 'section_label' ] ?? '' );
		$action = $items[ 0 ][ 'actions' ][ 0 ] ?? [];
		$this->assertSame( 'navigate', $action[ 'type' ] ?? '' );
		$this->assertSame( [], $action[ 'attributes' ] ?? [] );
		$this->assertSame( 'https://example.com/update', $action[ 'href' ] ?? '' );
	}
}
