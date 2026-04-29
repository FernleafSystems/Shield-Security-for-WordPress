<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueAssetMetadataResolver,
	ActionsQueueScanAssetCardsBuilder,
	ActionsQueueScanResultsTableBuilder,
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

			protected function buildScanResultsTableBuilder() :ActionsQueueScanResultsTableBuilder {
				return new class extends ActionsQueueScanResultsTableBuilder {
					public function buildPluginTable( string $pluginFile, ?array $options = null ) :array {
						$displayOptions = ( new \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScanResultsDisplayOptions() )
							->normalize( $options );
						return [
							'table_action_attr' => \json_encode( [
								'type'                    => 'plugin',
								'file'                    => $pluginFile,
								'display_context'         => 'actions_queue',
								'results_display_options' => $displayOptions,
							], \JSON_THROW_ON_ERROR ),
						];
					}
				};
			}

			public function getSeenOptions() :array {
				return $this->seenOptions;
			}
		};

		$builder = new class( $assetCardsBuilder ) extends ScansResultsViewBuilder {

			private ActionsQueueScanAssetCardsBuilder $assetCardsBuilder;

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
		$tableAction = $this->decodeJsonAttr( $pane[ 'cards' ][ 0 ][ 'table' ][ 'table_action_attr' ] );
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
		$this->assertSame( '', $queuePane[ 'cards' ][ 0 ][ 'body_notice' ] );
		$this->assertSame( '', $queuePane[ 'cards' ][ 0 ][ 'body_notice_variant' ] );
		$this->assertSame( '/wp-admin/plugins.php', $queuePane[ 'cards' ][ 0 ][ 'actions' ][ 0 ][ 'href' ] );
		$this->assertSame( '1', $queuePane[ 'cards' ][ 0 ][ 'panel_data' ][ 'actions-queue-asset-panel-loaded' ] ?? '' );
		$this->assertSame( '0', $queuePane[ 'cards' ][ 0 ][ 'panel_data' ][ 'actions-queue-asset-panel-lazy' ] ?? '' );
		$queueTableAction = $this->decodeJsonAttr( $queuePane[ 'cards' ][ 0 ][ 'table' ][ 'table_action_attr' ] );
		$railTableAction = $this->decodeJsonAttr( $railPane[ 'items' ][ 0 ][ 'expansion' ][ 'table' ][ 'table_action_attr' ] );
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
		$this->assertSame( '/index.php', $pane[ 'cards' ][ 1 ][ 'meta_text' ] );
		$this->assertSame( 'info', $pane[ 'cards' ][ 1 ][ 'body_notice_variant' ] );
	}

	public function test_file_locker_groups_problem_pending_and_good_locks_into_distinct_sections_without_changing_issue_count() :void {
		$builder = $this->createBuilder( [
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

		$pane = $builder->buildActionsQueueFileLockerPane();
		$items = $pane[ 'cards' ] ?? [];

		$this->assertCount( 3, $items );
		$this->assertSame( 'warning', $items[ 0 ][ 'status' ] ?? '' );
		$this->assertSame( 'neutral', $items[ 1 ][ 'status' ] ?? '' );
		$this->assertSame( 'good', $items[ 2 ][ 'status' ] ?? '' );
	}

	public function test_file_locker_returns_empty_when_disabled() :void {
		$builder = $this->createBuilder( [
			'problemFileLocks'  => [ (object)[ 'path' => '/test', 'detected_at' => 1, 'hash_current' => '' ] ],
			'tabAvailability'   => [
				'file_locker' => [
					'is_available'          => false,
					'show_in_actions_queue' => false,
					'disabled_message'      => '',
					'disabled_status'       => 'neutral',
					'disabled_actions'      => [],
				],
			],
		] );

		$pane = $builder->buildActionsQueueFileLockerPane();
		$this->assertTrue( $pane[ 'is_disabled' ] );
		$this->assertSame( [], $pane[ 'cards' ] );
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
			'tabAvailability'        => [
				'abandoned' => [
					'is_available'          => true,
					'show_in_actions_queue' => true,
					'disabled_message'      => '',
					'disabled_status'       => 'neutral',
					'disabled_actions'      => [],
				],
			],
		] );

		$sectionLabels = \array_column( $builder->buildRailPaneData( 'vulnerabilities' )[ 'items' ] ?? [], 'section_label' );
		$this->assertContains( $vulnerableLabel, $sectionLabels );
		$this->assertNotContains( $abandonedLabel, $sectionLabels );

		$abandonedSectionLabels = \array_column( $builder->buildRailPaneData( 'abandoned' )[ 'items' ] ?? [], 'section_label' );
		$this->assertContains( $abandonedLabel, $abandonedSectionLabels );
		$this->assertNotContains( $vulnerableLabel, $abandonedSectionLabels );
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

		$actions = $builder->buildRailPaneData( 'vulnerabilities' )[ 'items' ][ 0 ][ 'actions' ] ?? [];
		$this->assertCount( 2, $actions );
		$this->assertSame( 'update', $actions[ 0 ][ 'type' ] ?? '' );
		$this->assertSame( '/wp-admin/update-core.php', $actions[ 0 ][ 'href' ] ?? '' );
		$this->assertSame( 'navigate', $actions[ 1 ][ 'type' ] ?? '' );
		$this->assertSame( 'https://lookup.example/plugin', $actions[ 1 ][ 'href' ] ?? '' );
		$this->assertSame( '_blank', $actions[ 1 ][ 'attributes' ][ 'target' ] ?? '' );
	}

}
