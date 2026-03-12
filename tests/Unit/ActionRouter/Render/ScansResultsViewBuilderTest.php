<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScansResultsViewBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\General;

class ScansResultsViewBuilderTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias( static fn( string $single, string $plural, int $count ) :string => $count === 1 ? $single : $plural );
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
		$GLOBALS[ 'wp_version' ] = '6.7';
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->installServices();
	}

	protected function tearDown() :void {
		unset( $GLOBALS[ 'wp_version' ] );
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	// ── Existing tests (updated for behavioral changes) ──

	public function test_build_prefers_summary_rows_and_hides_empty_asset_and_vulnerability_tabs() :void {
		$builder = $this->createBuilder( [
			'summaryRows'  => [
				[ 'key' => 'wp_files', 'label' => 'WP Files', 'count' => 2 ],
			],
			'assessmentRows' => [
				[ 'key' => 'assessment', 'label' => 'Assessment', 'status' => 'good', 'description' => 'Fine' ],
			],
			'wordpressPayload'  => $this->buildSectionPayload( 'rendered-wordpress', 2 ),
			'pluginsPayload'    => $this->buildSectionPayload( 'rendered-plugins', 0 ),
			'themesPayload'     => $this->buildSectionPayload( 'rendered-themes', 3 ),
			'malwarePayload'    => $this->buildSectionPayload( 'rendered-malware', 1 ),
			'fileLockerPayload' => $this->buildFileLockerPayload( 'rendered-file-locker', false ),
			'vulnerabilities'   => $this->buildEmptyVulnerabilities(),
			'wordpressEnabled'        => true,
			'pluginsEnabled'          => true,
			'themesEnabled'           => true,
			'vulnerabilitiesEnabled'  => false,
			'malwareEnabled'          => true,
		] );

		$renderData = $builder->build();
		$tabs = $renderData[ 'vars' ][ 'tabs' ] ?? [];
		$rail = $renderData[ 'vars' ][ 'rail' ] ?? [];
		$railTabs = $renderData[ 'vars' ][ 'rail_tabs' ] ?? [];

		$this->assertSame( [ 'summary', 'wordpress', 'themes', 'malware', 'file_locker' ], \array_column( $tabs, 'key' ) );
		$this->assertSame( [ 'summary', 'wordpress', 'plugins', 'themes', 'malware', 'file_locker' ], \array_column( $railTabs, 'key' ) );
		$this->assertSame( [ 'summary', 'wordpress', 'plugins', 'themes', 'malware', 'file_locker' ], \array_column( $rail[ 'items' ] ?? [], 'key' ) );
		$this->assertTrue( (bool)( $tabs[ 0 ][ 'is_active' ] ?? false ) );
		// Assessment rows now always populated
		$this->assertNotEmpty( $renderData[ 'vars' ][ 'assessment_rows' ] );
		$this->assertSame( 'rendered-wordpress', $renderData[ 'content' ][ 'section' ][ 'wordpress' ] ?? '' );
		$this->assertSame( 'rendered-themes', $renderData[ 'content' ][ 'section' ][ 'themes' ] ?? '' );
	}

	public function test_build_uses_assessment_rows_when_summary_is_empty_and_shows_vulnerabilities() :void {
		$builder = $this->createBuilder( [
			'assessmentRows' => [
				[ 'key' => 'wp_files', 'label' => 'WordPress Core Files', 'status' => 'good', 'description' => 'OK' ],
			],
			'wordpressPayload'  => $this->buildSectionPayload( 'rendered-wordpress', 9 ),
			'pluginsPayload'    => $this->buildSectionPayload( 'rendered-plugins', 4 ),
			'themesPayload'     => $this->buildSectionPayload( 'rendered-themes', 0 ),
			'malwarePayload'    => $this->buildSectionPayload( 'rendered-malware', 0 ),
			'fileLockerPayload' => $this->buildFileLockerPayload( 'rendered-file-locker', false ),
			'vulnerabilities'   => [
				'count'    => 2,
				'status'   => 'critical',
				'sections' => [
					'vulnerable' => [
						'label' => 'Known Vulnerabilities',
						'items' => [
							[
								'label'       => 'Vulnerable Plugin',
								'description' => '1 known vulnerability needs review.',
								'severity'    => 'critical',
								'count'       => 1,
							],
						],
					],
					'abandoned' => [
						'label' => 'Abandoned Assets',
						'items' => [
							[
								'label'       => 'Abandoned Theme',
								'description' => 'Abandoned.',
								'severity'    => 'warning',
								'count'       => 1,
							],
						],
					],
				],
			],
			'wordpressEnabled'       => false,
			'pluginsEnabled'         => true,
			'themesEnabled'          => true,
			'vulnerabilitiesEnabled' => true,
			'malwareEnabled'         => false,
		] );

		$renderData = $builder->build();
		$tabs = $renderData[ 'vars' ][ 'tabs' ] ?? [];
		$railTabs = $renderData[ 'vars' ][ 'rail_tabs' ] ?? [];

		$this->assertSame( [ 'summary', 'plugins', 'vulnerabilities', 'malware', 'file_locker' ], \array_column( $tabs, 'key' ) );
		$this->assertSame( [ 'summary', 'plugins', 'themes', 'vulnerabilities', 'file_locker' ], \array_column( $railTabs, 'key' ) );
		$this->assertSame( [], $renderData[ 'vars' ][ 'summary_rows' ] ?? null );
		$this->assertNotEmpty( $renderData[ 'vars' ][ 'assessment_rows' ] );
		$this->assertSame( 2, (int)( $tabs[ 2 ][ 'count' ] ?? 0 ) );
		// Vulnerability tab has critical status
		$vulnTab = $this->findTabByKey( $railTabs, 'vulnerabilities' );
		$this->assertSame( 'critical', $vulnTab[ 'status' ] ?? '' );
		$this->assertSame( 'rendered-file-locker', $renderData[ 'content' ][ 'section' ][ 'filelocker' ] ?? '' );
	}

	// ── Tab assembly: icon_class passthrough ──

	public function test_rail_tabs_include_icon_class_for_all_tabs() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled'       => true,
			'pluginsEnabled'         => true,
			'themesEnabled'          => true,
			'vulnerabilitiesEnabled' => true,
			'malwareEnabled'         => true,
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];

		foreach ( $railTabs as $tab ) {
			$this->assertArrayHasKey( 'icon_class', $tab, 'Tab '.$tab[ 'key' ].' should have icon_class' );
			$this->assertNotEmpty( $tab[ 'icon_class' ], 'Tab '.$tab[ 'key' ].' icon_class should not be empty' );
		}
	}

	public function test_rail_items_include_icon_class_for_all_tabs() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled'       => true,
			'pluginsEnabled'         => true,
			'themesEnabled'          => true,
			'vulnerabilitiesEnabled' => true,
			'malwareEnabled'         => true,
		] );

		$railItems = $builder->build()[ 'vars' ][ 'rail' ][ 'items' ] ?? [];

		foreach ( $railItems as $item ) {
			$this->assertArrayHasKey( 'icon_class', $item, 'Rail item '.$item[ 'key' ].' should have icon_class' );
			$this->assertNotEmpty( $item[ 'icon_class' ], 'Rail item '.$item[ 'key' ].' icon_class should not be empty' );
		}
	}

	public function test_rail_tabs_expose_complete_local_contract_fields() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled' => true,
			'pluginsEnabled'   => true,
		] );

		$summaryTab = $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'summary' );
		$pluginsTab = $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'plugins' );

		foreach ( [ 'items', 'is_loaded', 'is_disabled', 'disabled_message', 'disabled_status', 'render_action', 'show_count_placeholder' ] as $key ) {
			$this->assertArrayHasKey( $key, $summaryTab, 'Summary tab should expose '.$key );
			$this->assertArrayHasKey( $key, $pluginsTab, 'Plugins tab should expose '.$key );
		}

		$this->assertSame( [], $summaryTab[ 'render_action' ] );
		$this->assertTrue( $summaryTab[ 'is_loaded' ] );
		$this->assertFalse( $pluginsTab[ 'is_disabled' ] );
	}

	// ── Tab assembly: count derivation ──

	public function test_rail_tab_count_excludes_good_status_items() :void {
		$issueItems = [
			$this->makeDetailRow( 'Bad Plugin', 'warning', 3 ),
		];
		$goodItems = [
			$this->makeDetailRow( 'Clean Plugin', 'good' ),
			$this->makeDetailRow( 'Clean Plugin 2', 'good' ),
		];

		$builder = $this->createBuilder( [
			'pluginsEnabled' => true,
			'pluginRailItems' => \array_merge( $issueItems, $goodItems ),
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$pluginsTab = $this->findTabByKey( $railTabs, 'plugins' );

		$this->assertSame( 1, $pluginsTab[ 'count' ] ?? -1, 'Count should only include non-good items' );
	}

	// ── Tab assembly: status cascade ──

	public function test_summary_tab_status_reflects_highest_child_severity() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled' => true,
			'malwareEnabled'   => true,
			'afsDisplayItems'  => [
				$this->makeAfsItem( 'is_in_core' ),
			],
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$summaryTab = $this->findTabByKey( $railTabs, 'summary' );

		$this->assertSame( 'critical', $summaryTab[ 'status' ] ?? '', 'Summary should inherit critical from WordPress tab' );
	}

	public function test_summary_tab_status_is_good_when_all_children_are_good() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled' => true,
			'malwareEnabled'   => true,
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$summaryTab = $this->findTabByKey( $railTabs, 'summary' );

		$this->assertSame( 'good', $summaryTab[ 'status' ] ?? '' );
	}

	// ── Summary pane: mixed sections ──

	public function test_summary_items_show_attention_and_all_clear_sections_when_issues_exist() :void {
		$builder = $this->createBuilder( [
			'summaryRows' => [
				[ 'label' => 'WordPress Files', 'text' => 'Issues found', 'severity' => 'critical', 'count' => 3 ],
			],
			'assessmentRows' => [
				[ 'label' => 'Plugin Files', 'description' => 'All clear', 'status' => 'good', 'status_icon_class' => '', 'status_label' => 'Good' ],
				[ 'label' => 'Malware', 'description' => 'Problem', 'status' => 'warning', 'status_icon_class' => '', 'status_label' => 'Warning' ],
			],
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$summaryTab = $this->findTabByKey( $railTabs, 'summary' );
		$items = $summaryTab[ 'items' ] ?? [];
		$sectionLabels = \array_values( \array_unique( \array_filter( \array_column( $items, 'section_label' ) ) ) );

		$this->assertCount( 2, $sectionLabels, 'Should have both section labels' );
		$this->assertSame( 'Needs attention', $sectionLabels[ 0 ] );
		$this->assertSame( 'All clear', $sectionLabels[ 1 ] );
		// Only good assessments appear in "All clear" section
		$allClearItems = \array_filter( $items, static fn( array $item ) => ( $item[ 'section_label' ] ?? '' ) === 'All clear' );
		$this->assertCount( 1, $allClearItems, 'Only good assessments should be in All clear section' );
	}

	public function test_detail_rows_expose_complete_local_contract_fields() :void {
		$builder = $this->createBuilder( [
			'summaryRows' => [
				[ 'label' => 'WordPress Files', 'text' => 'Issues found', 'severity' => 'critical', 'count' => 3 ],
			],
		] );

		$summaryTab = $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'summary' );
		$row = $summaryTab[ 'items' ][ 0 ] ?? [];

		foreach ( [ 'expand_target', 'expansion_table', 'section_label', 'actions', 'attributes' ] as $key ) {
			$this->assertArrayHasKey( $key, $row, 'Detail rows should expose '.$key );
		}

		$this->assertSame( '', $row[ 'expand_target' ] );
		$this->assertSame( [], $row[ 'expansion_table' ] );
		$this->assertSame( 'Needs attention', $row[ 'section_label' ] );
	}

	public function test_summary_items_show_only_assessments_when_no_issues() :void {
		$builder = $this->createBuilder( [
			'summaryRows' => [],
			'assessmentRows' => [
				[ 'label' => 'WordPress Core', 'description' => 'All clear', 'status' => 'good', 'status_icon_class' => '', 'status_label' => 'Good' ],
				[ 'label' => 'Malware', 'description' => 'All clear', 'status' => 'good', 'status_icon_class' => '', 'status_label' => 'Good' ],
			],
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$summaryTab = $this->findTabByKey( $railTabs, 'summary' );
		$items = $summaryTab[ 'items' ] ?? [];

		$this->assertCount( 2, $items );
		$this->assertSame(
			[ '', '' ],
			\array_column( $items, 'section_label' ),
			'Assessment-only items should keep an empty normalized section_label'
		);
	}

	// ── WordPress pane: good fallback ──

	public function test_wordpress_pane_returns_no_items_when_no_core_issues() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled' => true,
			'afsDisplayItems'  => [],
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$wpTab = $this->findTabByKey( $railTabs, 'wordpress' );
		$items = $wpTab[ 'items' ] ?? [];

		$this->assertSame( [], $items );
		$this->assertSame( 'good', $wpTab[ 'status' ] ?? '' );
		$this->assertSame( 0, $wpTab[ 'count' ] ?? -1 );
	}

	public function test_wordpress_pane_shows_critical_items_for_core_issues() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled' => true,
			'afsDisplayItems'  => [
				$this->makeAfsItem( 'is_in_core', [ 'is_checksumfail' => 1, 'path_fragment' => 'wp-admin/admin.php' ] ),
				$this->makeAfsItem( 'is_in_core', [ 'is_missing' => 1, 'path_fragment' => 'wp-includes/class-wp.php' ] ),
			],
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$wpTab = $this->findTabByKey( $railTabs, 'wordpress' );
		$items = $wpTab[ 'items' ] ?? [];

		$this->assertCount( 2, $items );
		foreach ( $items as $item ) {
			$this->assertSame( 'critical', $item[ 'status' ] ?? '' );
		}
		$this->assertSame( 2, $wpTab[ 'count' ] ?? -1, 'WordPress tab count reflects non-good items' );
	}

	// ── Malware pane: good fallback ──

	public function test_malware_pane_returns_no_items_when_no_threats() :void {
		$builder = $this->createBuilder( [
			'malwareEnabled'  => true,
			'afsDisplayItems' => [],
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$malwareTab = $this->findTabByKey( $railTabs, 'malware' );
		$items = $malwareTab[ 'items' ] ?? [];

		$this->assertSame( [], $items );
		$this->assertSame( 'good', $malwareTab[ 'status' ] ?? '' );
		$this->assertSame( 0, $malwareTab[ 'count' ] ?? -1 );
	}

	public function test_plugin_pane_data_returns_disabled_state_when_scan_is_unavailable() :void {
		$builder = $this->createBuilder( [
			'pluginsEnabled' => false,
			'tabAvailability' => [
				'plugins' => [
					'is_available' => false,
					'show_in_actions_queue' => true,
					'disabled_message' => 'Scanning Plugin & Theme Files is available only with the Pro version of Shield.',
					'disabled_status' => 'neutral',
				],
			],
		] );

		$pane = $builder->buildRailPaneData( 'plugins' );

		$this->assertTrue( (bool)( $pane[ 'is_disabled' ] ?? false ) );
		$this->assertSame( 'neutral', $pane[ 'status' ] ?? '' );
		$this->assertSame( 0, $pane[ 'count_items' ] ?? -1 );
		$this->assertSame( [], $pane[ 'items' ] ?? [ 'unexpected' ] );
		$this->assertSame(
			'Scanning Plugin & Theme Files is available only with the Pro version of Shield.',
			$pane[ 'disabled_message' ] ?? ''
		);
	}

	public function test_theme_pane_data_returns_disabled_state_when_scan_is_unavailable() :void {
		$builder = $this->createBuilder( [
			'themesEnabled' => false,
			'tabAvailability' => [
				'themes' => [
					'is_available' => false,
					'show_in_actions_queue' => true,
					'disabled_message' => 'Theme File Scanning is not enabled.',
					'disabled_status' => 'neutral',
				],
			],
		] );

		$pane = $builder->buildRailPaneData( 'themes' );

		$this->assertTrue( (bool)( $pane[ 'is_disabled' ] ?? false ) );
		$this->assertSame( 'Theme File Scanning is not enabled.', $pane[ 'disabled_message' ] ?? '' );
		$this->assertSame( [], $pane[ 'items' ] ?? [ 'unexpected' ] );
	}

	public function test_vulnerability_pane_data_returns_disabled_state_when_scans_are_unavailable() :void {
		$builder = $this->createBuilder( [
			'vulnerabilitiesEnabled' => false,
			'tabAvailability' => [
				'vulnerabilities' => [
					'is_available' => false,
					'show_in_actions_queue' => true,
					'disabled_message' => 'Vulnerability Scanning is not enabled.',
					'disabled_status' => 'neutral',
				],
			],
		] );

		$pane = $builder->buildRailPaneData( 'vulnerabilities' );

		$this->assertTrue( (bool)( $pane[ 'is_disabled' ] ?? false ) );
		$this->assertSame( 'neutral', $pane[ 'status' ] ?? '' );
		$this->assertSame( 'Vulnerability Scanning is not enabled.', $pane[ 'disabled_message' ] ?? '' );
		$this->assertSame( [], $pane[ 'items' ] ?? [ 'unexpected' ] );
	}

	public function test_malware_pane_data_returns_disabled_state_when_scan_is_unavailable() :void {
		$builder = $this->createBuilder( [
			'malwareEnabled' => false,
			'tabAvailability' => [
				'malware' => [
					'is_available' => false,
					'show_in_actions_queue' => true,
					'disabled_message' => 'Malware Scanning is not enabled.',
					'disabled_status' => 'neutral',
				],
			],
		] );

		$pane = $builder->buildRailPaneData( 'malware' );

		$this->assertTrue( (bool)( $pane[ 'is_disabled' ] ?? false ) );
		$this->assertSame( 'Malware Scanning is not enabled.', $pane[ 'disabled_message' ] ?? '' );
		$this->assertSame( [], $pane[ 'items' ] ?? [ 'unexpected' ] );
	}

	// ── File locker pane: mixed sections ──

	public function test_actions_queue_plugin_pane_reuses_shared_issue_records_for_cards_and_rail_rows() :void {
		$records = [
			$this->makePluginThemeIssueRecord( 'plugin', 'example-plugin', 'Example Plugin', 'example-plugin/example-plugin.php', 3 ),
		];
		$builder = $this->createBuilder( [
			'pluginsEnabled'     => true,
			'pluginIssueRecords' => $records,
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
		$this->assertSame( 'plugin', $queuePane[ 'cards' ][ 0 ][ 'table' ][ 'subject_type' ] );
		$this->assertSame( 'example-plugin/example-plugin.php', $queuePane[ 'cards' ][ 0 ][ 'table' ][ 'subject_id' ] );
		$this->assertCount( 1, $railPane[ 'items' ] );
		$this->assertSame( 'scan-files-plugin-example-plugin', $railPane[ 'items' ][ 0 ][ 'expand_target' ] );
		$this->assertSame(
			$queuePane[ 'cards' ][ 0 ][ 'table' ][ 'subject_id' ],
			$railPane[ 'items' ][ 0 ][ 'expansion_table' ][ 'subject_id' ]
		);
	}

	public function test_actions_queue_theme_pane_returns_disabled_state_when_scan_is_unavailable() :void {
		$builder = $this->createBuilder( [
			'themesEnabled' => false,
			'tabAvailability' => [
				'themes' => [
					'is_available' => false,
					'show_in_actions_queue' => true,
					'disabled_message' => 'Theme File Scanning is not enabled.',
					'disabled_status' => 'neutral',
				],
			],
		] );

		$pane = $builder->buildActionsQueueThemesPane();

		$this->assertTrue( $pane[ 'is_disabled' ] );
		$this->assertSame( 'Theme File Scanning is not enabled.', $pane[ 'disabled_message' ] );
		$this->assertSame( [], $pane[ 'cards' ] );
	}

	public function test_actions_queue_file_locker_pane_uses_per_card_panels_and_orders_problem_locks_first() :void {
		$problemLock = (object)[ 'id' => 14, 'path' => '/wp-config.php', 'detected_at' => 1000, 'hash_current' => '' ];
		$goodLock = (object)[ 'id' => 15, 'path' => '/.htaccess', 'detected_at' => 0, 'hash_current' => 'abc123' ];
		$builder = $this->createBuilder( [
			'problemFileLocks' => [ $problemLock ],
			'goodFileLocks'    => [ $goodLock ],
		] );

		$pane = $builder->buildActionsQueueFileLockerPane();

		$this->assertFalse( $pane[ 'is_disabled' ] );
		$this->assertCount( 2, $pane[ 'cards' ] );
		$this->assertSame( 'warning', $pane[ 'cards' ][ 0 ][ 'status' ] );
		$this->assertSame( 'good', $pane[ 'cards' ][ 1 ][ 'status' ] );
		$this->assertSame( 'actions-queue-filelocker-card-14', $pane[ 'cards' ][ 0 ][ 'panel_id' ] );
		$this->assertSame( 'actions-queue-filelocker-14', $pane[ 'cards' ][ 0 ][ 'panel_target' ] );
		$this->assertSame( '/wp-config.php', $pane[ 'cards' ][ 0 ][ 'meta_text' ] );
		$this->assertFalse( $pane[ 'cards' ][ 0 ][ 'show_meta_in_tile' ] );
		$this->assertSame( 'filelocker_showdiff', $pane[ 'cards' ][ 0 ][ 'render_action' ][ 'render_slug' ] );
		$this->assertSame( 14, $pane[ 'cards' ][ 0 ][ 'render_action' ][ 'rid' ] );
	}

	public function test_file_locker_shows_problem_and_good_locks_with_section_labels() :void {
		$problemLock = (object)[ 'id' => 21, 'path' => '/wp-config.php', 'detected_at' => 1000, 'hash_current' => '' ];
		$goodLock = (object)[ 'id' => 22, 'path' => '/.htaccess', 'detected_at' => 0, 'hash_current' => 'abc123' ];

		$builder = $this->createBuilder( [
			'fileLockerPayload' => $this->buildFileLockerPayload( '', true ),
			'problemFileLocks'  => [ $problemLock ],
			'goodFileLocks'     => [ $goodLock ],
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$flTab = $this->findTabByKey( $railTabs, 'file_locker' );
		$items = $flTab[ 'items' ] ?? [];

		$this->assertCount( 2, $items );
		$this->assertSame( 'warning', $items[ 0 ][ 'status' ] ?? '' );
		$this->assertSame( 'Needs attention', $items[ 0 ][ 'section_label' ] ?? '' );
		$this->assertSame( 'good', $items[ 1 ][ 'status' ] ?? '' );
		$this->assertSame( 'All clear', $items[ 1 ][ 'section_label' ] ?? '' );
		$this->assertSame( 1, $flTab[ 'count' ] ?? -1, 'Count should only include problem locks' );
		$this->assertSame( 'warning', $flTab[ 'status' ] ?? '' );
	}

	public function test_file_locker_returns_empty_when_disabled() :void {
		$builder = $this->createBuilder( [
			'fileLockerPayload' => $this->buildFileLockerPayload( '', false ),
			'problemFileLocks'  => [ (object)[ 'path' => '/test', 'detected_at' => 1, 'hash_current' => '' ] ],
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$flTab = $this->findTabByKey( $railTabs, 'file_locker' );

		$this->assertEmpty( $flTab[ 'items' ] ?? [] );
		$this->assertSame( 'good', $flTab[ 'status' ] ?? '' );
	}

	// ── Plugin/theme items passthrough ──

	public function test_plugin_rail_items_pass_through_to_tab() :void {
		$items = [
			$this->makeDetailRow( 'Bad Plugin', 'warning', 5 ),
		];

		$builder = $this->createBuilder( [
			'pluginsEnabled'   => true,
			'pluginRailItems'  => $items,
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$pluginsTab = $this->findTabByKey( $railTabs, 'plugins' );

		$this->assertCount( 1, $pluginsTab[ 'items' ] ?? [] );
		$this->assertSame( 'warning', $pluginsTab[ 'status' ] ?? '' );
	}

	public function test_theme_rail_items_pass_through_to_tab() :void {
		$items = [
			$this->makeDetailRow( 'Good Theme', 'good' ),
		];

		$builder = $this->createBuilder( [
			'themesEnabled'   => true,
			'themeRailItems'  => $items,
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$themesTab = $this->findTabByKey( $railTabs, 'themes' );

		$this->assertCount( 1, $themesTab[ 'items' ] ?? [] );
		$this->assertSame( 'good', $themesTab[ 'status' ] ?? '' );
		$this->assertSame( 0, $themesTab[ 'count' ] ?? -1 );
	}

	// ── Tab ordering ──

	public function test_rail_tab_ordering_follows_spec() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled'       => true,
			'pluginsEnabled'         => true,
			'themesEnabled'          => true,
			'vulnerabilitiesEnabled' => true,
			'malwareEnabled'         => true,
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$keys = \array_column( $railTabs, 'key' );

		$this->assertSame( [
			'summary', 'wordpress', 'plugins', 'themes', 'vulnerabilities', 'malware', 'file_locker',
		], $keys );
	}

	public function test_legacy_tabs_follow_the_canonical_order_when_all_legacy_tabs_are_visible() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled'       => true,
			'pluginsEnabled'         => true,
			'themesEnabled'          => true,
			'vulnerabilitiesEnabled' => true,
			'malwareEnabled'         => true,
			'wordpressPayload'       => $this->buildSectionPayload( 'rendered-wordpress', 1 ),
			'pluginsPayload'         => $this->buildSectionPayload( 'rendered-plugins', 1 ),
			'themesPayload'          => $this->buildSectionPayload( 'rendered-themes', 1 ),
			'malwarePayload'         => $this->buildSectionPayload( 'rendered-malware', 1 ),
			'fileLockerPayload'      => $this->buildFileLockerPayload( 'rendered-file-locker', true, 1 ),
			'vulnerabilities'        => [
				'count'    => 1,
				'status'   => 'warning',
				'sections' => [],
			],
		] );

		$renderData = $builder->build();

		$this->assertSame(
			\array_column( $renderData[ 'vars' ][ 'rail_tabs' ] ?? [], 'key' ),
			\array_column( $renderData[ 'vars' ][ 'tabs' ] ?? [], 'key' )
		);
	}

	public function test_first_rail_tab_is_always_active() :void {
		$builder = $this->createBuilder();

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];

		$this->assertTrue( (bool)( $railTabs[ 0 ][ 'is_active' ] ?? false ) );
		$inactiveCount = \count( \array_filter( \array_slice( $railTabs, 1 ), static fn( array $t ) => !empty( $t[ 'is_active' ] ) ) );
		$this->assertSame( 0, $inactiveCount, 'Only the first tab should be active' );
	}

	// ── Vulnerability items ──

	public function test_vulnerability_items_preserve_section_labels() :void {
		$builder = $this->createBuilder( [
			'vulnerabilitiesEnabled' => true,
			'vulnerabilities' => [
				'count'    => 2,
				'status'   => 'critical',
				'sections' => [
					'vulnerable' => [
						'label' => 'Known Vulnerabilities',
						'items' => [
							[ 'label' => 'Vuln 1', 'description' => 'Desc', 'severity' => 'critical', 'count' => 1 ],
						],
					],
					'abandoned' => [
						'label' => 'Abandoned',
						'items' => [
							[ 'label' => 'Old Plugin', 'description' => 'Desc', 'severity' => 'warning', 'count' => 1 ],
						],
					],
				],
			],
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$vulnTab = $this->findTabByKey( $railTabs, 'vulnerabilities' );
		$items = $vulnTab[ 'items' ] ?? [];
		$sectionLabels = \array_column( $items, 'section_label' );

		$this->assertContains( 'Known Vulnerabilities', $sectionLabels );
		$this->assertContains( 'Abandoned', $sectionLabels );
	}

	public function test_vulnerability_items_keep_native_and_investigate_actions() :void {
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
										'href'  => '/shield/investigate/plugin#tab-navlink-plugin-vulnerabilities',
										'label' => 'View vulnerability results',
										'type'  => 'navigate',
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

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$actions = $this->findTabByKey( $railTabs, 'vulnerabilities' )[ 'items' ][ 0 ][ 'actions' ] ?? [];

		$this->assertCount( 3, $actions );
		$this->assertSame( 'update', $actions[ 0 ][ 'type' ] ?? '' );
		$this->assertSame( '/wp-admin/update-core.php', $actions[ 0 ][ 'href' ] ?? '' );
		$this->assertSame( '/shield/investigate/plugin#tab-navlink-plugin-vulnerabilities', $actions[ 1 ][ 'href' ] ?? '' );
		$this->assertStringContainsString( '#tab-navlink-plugin-vulnerabilities', $actions[ 1 ][ 'href' ] ?? '' );
		$this->assertSame( 'navigate', $actions[ 2 ][ 'type' ] ?? '' );
		$this->assertSame( 'https://lookup.example/plugin', $actions[ 2 ][ 'href' ] ?? '' );
		$this->assertSame( '_blank', $actions[ 2 ][ 'attributes' ][ 'target' ] ?? '' );
	}

	// ── AFS display items caching ──

	public function test_afs_display_items_are_shared_between_wordpress_and_malware() :void {
		$coreItem = $this->makeAfsItem( 'is_in_core', [ 'is_checksumfail' => 1, 'path_fragment' => 'wp-admin/x.php' ] );
		$malItem = $this->makeAfsItem( 'is_mal', [ 'path_fragment' => 'evil.php' ] );

		$builder = $this->createBuilder( [
			'wordpressEnabled' => true,
			'malwareEnabled'   => true,
			'afsDisplayItems'  => [ $coreItem, $malItem ],
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$wpTab = $this->findTabByKey( $railTabs, 'wordpress' );
		$malwareTab = $this->findTabByKey( $railTabs, 'malware' );

		$this->assertCount( 1, $wpTab[ 'items' ] ?? [] );
		$this->assertCount( 1, $malwareTab[ 'items' ] ?? [] );
		$this->assertSame( 'critical', $wpTab[ 'status' ] ?? '' );
		$this->assertSame( 'critical', $malwareTab[ 'status' ] ?? '' );
	}

	public function test_clean_wordpress_pane_returns_empty_items_with_good_status() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled' => true,
			'afsDisplayItems'  => [],
		] );

		$pane = $builder->buildRailPaneData( 'wordpress' );

		$this->assertSame( 'good', $pane[ 'status' ] ?? '' );
		$this->assertSame( 0, $pane[ 'count_items' ] ?? -1 );
		$this->assertSame( [], $pane[ 'items' ] ?? null );
	}

	public function test_clean_malware_pane_returns_empty_items_with_good_status() :void {
		$builder = $this->createBuilder( [
			'malwareEnabled' => true,
			'afsDisplayItems' => [],
		] );

		$pane = $builder->buildRailPaneData( 'malware' );

		$this->assertSame( 'good', $pane[ 'status' ] ?? '' );
		$this->assertSame( 0, $pane[ 'count_items' ] ?? -1 );
		$this->assertSame( [], $pane[ 'items' ] ?? null );
	}

	// ── Summary rail-switch actions (Task 6) ──

	public function test_summary_items_with_known_keys_get_row_level_rail_switch_attributes() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled'       => true,
			'pluginsEnabled'         => true,
			'themesEnabled'          => true,
			'vulnerabilitiesEnabled' => true,
			'malwareEnabled'         => true,
			'summaryRows' => [
				[ 'key' => 'wp_files', 'label' => 'WP Files', 'text' => 'Issues', 'severity' => 'critical', 'count' => 2 ],
				[ 'key' => 'plugin_files', 'label' => 'Plugin Files', 'text' => 'Issues', 'severity' => 'warning', 'count' => 1 ],
				[ 'key' => 'theme_files', 'label' => 'Theme Files', 'text' => 'Issues', 'severity' => 'warning', 'count' => 1 ],
				[ 'key' => 'malware', 'label' => 'Malware', 'text' => 'Issues', 'severity' => 'critical', 'count' => 1 ],
				[ 'key' => 'vulnerable_assets', 'label' => 'Vulns', 'text' => 'Issues', 'severity' => 'critical', 'count' => 3 ],
				[ 'key' => 'abandoned', 'label' => 'Abandoned', 'text' => 'Issues', 'severity' => 'warning', 'count' => 1 ],
			],
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$summaryTab = $this->findTabByKey( $railTabs, 'summary' );
		$items = $summaryTab[ 'items' ] ?? [];

		$expectedMapping = [
			'wp_files'          => 'wordpress',
			'plugin_files'      => 'plugins',
			'theme_files'       => 'themes',
			'malware'           => 'malware',
			'vulnerable_assets' => 'vulnerabilities',
			'abandoned'         => 'vulnerabilities',
		];

		$attentionItems = \array_filter( $items, static fn( array $item ) => ( $item[ 'section_label' ] ?? '' ) === 'Needs attention' );
		$this->assertCount( \count( $expectedMapping ), $attentionItems );

		foreach ( $attentionItems as $item ) {
			$this->assertSame( [], $item[ 'actions' ] ?? [], 'Item "'.$item[ 'title' ].'" should not render an extra action chip' );
			$this->assertNotEmpty( $item[ 'attributes' ][ 'data-shield-rail-switch' ] ?? '' );
			$this->assertSame( 'button', $item[ 'attributes' ][ 'role' ] ?? '' );
		}
	}

	public function test_summary_row_switch_attributes_map_to_correct_tabs() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled'       => true,
			'pluginsEnabled'         => true,
			'vulnerabilitiesEnabled' => true,
			'summaryRows' => [
				[ 'key' => 'wp_files', 'label' => 'WP Files', 'text' => 'Issues', 'severity' => 'critical', 'count' => 2 ],
				[ 'key' => 'plugin_files', 'label' => 'Plugin Files', 'text' => 'Issues', 'severity' => 'warning', 'count' => 1 ],
				[ 'key' => 'vulnerable_assets', 'label' => 'Vulns', 'text' => 'Issues', 'severity' => 'critical', 'count' => 3 ],
				[ 'key' => 'abandoned', 'label' => 'Abandoned', 'text' => 'Issues', 'severity' => 'warning', 'count' => 1 ],
			],
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$summaryTab = $this->findTabByKey( $railTabs, 'summary' );
		$items = $summaryTab[ 'items' ] ?? [];

		$targets = [];
		foreach ( $items as $item ) {
			if ( isset( $item[ 'attributes' ][ 'data-shield-rail-switch' ] ) ) {
				$targets[ $item[ 'title' ] ] = $item[ 'attributes' ][ 'data-shield-rail-switch' ];
			}
		}

		$this->assertSame( 'wordpress', $targets[ 'WP Files' ] ?? '' );
		$this->assertSame( 'plugins', $targets[ 'Plugin Files' ] ?? '' );
		$this->assertSame( 'vulnerabilities', $targets[ 'Vulns' ] ?? '' );
		$this->assertSame( 'vulnerabilities', $targets[ 'Abandoned' ] ?? '' );
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

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$summaryTab = $this->findTabByKey( $railTabs, 'summary' );
		$items = $summaryTab[ 'items' ] ?? [];

		$attentionItems = \array_filter( $items, static fn( array $item ) => ( $item[ 'section_label' ] ?? '' ) === 'Needs attention' );
		$this->assertCount( 1, $attentionItems );
		$action = \reset( $attentionItems )[ 'actions' ][ 0 ] ?? [];
		$this->assertSame( 'navigate', $action[ 'type' ] ?? '' );
		$this->assertSame( [], $action[ 'attributes' ] ?? [] );
		$this->assertSame( 'https://example.com/update', $action[ 'href' ] ?? '' );
	}

	// ── Helpers ──

	private function findTabByKey( array $tabs, string $key ) :array {
		foreach ( $tabs as $tab ) {
			if ( ( $tab[ 'key' ] ?? '' ) === $key ) {
				return $tab;
			}
		}
		$this->fail( 'Tab "'.$key.'" not found in: '.\implode( ', ', \array_column( $tabs, 'key' ) ) );
		return [];
	}

	private function makeDetailRow( string $title, string $status, ?int $countBadge = null ) :array {
		return [
			'title'        => $title,
			'description'  => '',
			'status'       => $status,
			'status_icon'  => null,
			'status_label' => null,
			'count_badge'  => $countBadge,
			'badge_status' => $countBadge !== null ? $status : null,
			'expandable'   => false,
			'expand_target' => '',
			'expansion_table' => [],
			'explanations' => [],
			'show_gear'    => false,
			'actions'      => [],
			'attributes'   => [],
			'section_label' => '',
		];
	}

	private function makePluginThemeIssueRecord(
		string $assetType,
		string $key,
		string $title,
		string $subjectId,
		int $countBadge
	) :array {
		return [
			'key'           => $key,
			'panel_id'      => 'actions-queue-'.$assetType.'-card-'.$key,
			'panel_target'  => 'actions-queue-'.$assetType.'-'.$key,
			'expand_target' => 'scan-files-'.$assetType.'-'.$key,
			'status'        => 'warning',
			'icon_class'    => $assetType === 'plugin' ? 'bi bi-plug-fill' : 'bi bi-palette-fill',
			'title'         => $title,
			'rail_title'    => '',
			'stat_text'     => $countBadge.' files need review',
			'meta_text'     => $subjectId,
			'show_meta_in_tile' => true,
			'count_badge'   => $countBadge,
			'actions'       => [
				[
					'type'       => 'deactivate',
					'label'      => 'Deactivate',
					'href'       => '/wp-admin/plugins.php',
					'icon'       => 'bi bi-power',
					'tooltip'    => 'Go to plugins',
					'attributes' => [],
				],
			],
			'table'         => [
				'subject_type' => $assetType,
				'subject_id'   => $subjectId,
			],
			'render_action' => [],
		];
	}

	private function makeAfsItem( string $flag, array $extra = [] ) :object {
		$item = (object)\array_merge( [
			'path_fragment'  => 'test/'.\uniqid( '', true ).'.php',
			'is_in_core'     => 0,
			'is_mal'         => 0,
			'is_missing'     => 0,
			'is_checksumfail' => 0,
			'is_unrecognised' => 0,
			'is_unidentified' => 0,
		], $extra, [ $flag => 1 ] );
		return $item;
	}

	private function buildSectionPayload( string $renderOutput, int $countItems ) :array {
		return [
			'render_output' => $renderOutput,
			'render_data'   => [
				'vars' => [
					'count_items' => $countItems,
				],
			],
		];
	}

	private function buildFileLockerPayload( string $renderOutput, bool $isEnabled, int $countItems = 0 ) :array {
		return [
			'render_output' => $renderOutput,
			'render_data'   => [
				'flags' => [
					'is_enabled'    => $isEnabled,
					'is_restricted' => false,
				],
				'vars' => [
					'file_locks' => [
						'count_items' => $countItems,
					],
				],
			],
		];
	}

	private function buildEmptyVulnerabilities() :array {
		return [
			'count'    => 0,
			'status'   => 'good',
			'sections' => [],
		];
	}

	private function createBuilder( array $overrides = [] ) :ScansResultsViewBuilderTestDouble {
		return new ScansResultsViewBuilderTestDouble(
			$overrides[ 'summaryRows' ] ?? [],
			$overrides[ 'assessmentRows' ] ?? [],
			$overrides[ 'wordpressPayload' ] ?? $this->buildSectionPayload( '', 0 ),
			$overrides[ 'pluginsPayload' ] ?? $this->buildSectionPayload( '', 0 ),
			$overrides[ 'themesPayload' ] ?? $this->buildSectionPayload( '', 0 ),
			$overrides[ 'malwarePayload' ] ?? $this->buildSectionPayload( '', 0 ),
			$overrides[ 'fileLockerPayload' ] ?? $this->buildFileLockerPayload( '', false ),
			$overrides[ 'vulnerabilities' ] ?? $this->buildEmptyVulnerabilities(),
			$overrides[ 'wordpressEnabled' ] ?? false,
			$overrides[ 'pluginsEnabled' ] ?? false,
			$overrides[ 'themesEnabled' ] ?? false,
			$overrides[ 'vulnerabilitiesEnabled' ] ?? false,
			$overrides[ 'malwareEnabled' ] ?? false,
			$overrides[ 'afsDisplayItems' ] ?? [],
			$overrides[ 'problemFileLocks' ] ?? [],
			$overrides[ 'goodFileLocks' ] ?? [],
			$overrides[ 'pluginRailItems' ] ?? [],
			$overrides[ 'themeRailItems' ] ?? [],
			$overrides[ 'pluginIssueRecords' ] ?? [],
			$overrides[ 'themeIssueRecords' ] ?? [],
			$overrides[ 'tabAvailability' ] ?? []
		);
	}

	private function installServices() :void {
		ServicesState::installItems( [
			'service_wpgeneral' => new class extends General {
				public function ajaxURL() :string {
					return '/admin-ajax.php';
				}
			},
		] );
	}
}

class ScansResultsViewBuilderTestDouble extends ScansResultsViewBuilder {

	private array $summaryRows;
	private array $assessmentRows;
	private array $wordpressPayload;
	private array $pluginsPayload;
	private array $themesPayload;
	private array $malwarePayload;
	private array $fileLockerPayload;
	private array $vulnerabilities;
	private bool $wordpressEnabled;
	private bool $pluginsEnabled;
	private bool $themesEnabled;
	private bool $vulnerabilitiesEnabled;
	private bool $malwareEnabled;
	private array $afsDisplayItems;
	private array $problemLocks;
	private array $goodLocks;
	private array $pluginRailItems;
	private array $themeRailItems;
	private array $pluginIssueRecords;
	private array $themeIssueRecords;
	private array $tabAvailability;

	public function __construct(
		array $summaryRows,
		array $assessmentRows,
		array $wordpressPayload,
		array $pluginsPayload,
		array $themesPayload,
		array $malwarePayload,
		array $fileLockerPayload,
		array $vulnerabilities,
		bool $wordpressEnabled,
		bool $pluginsEnabled = false,
		bool $themesEnabled = false,
		bool $vulnerabilitiesEnabled = false,
		bool $malwareEnabled = false,
		array $afsDisplayItems = [],
		array $problemLocks = [],
		array $goodLocks = [],
		array $pluginRailItems = [],
		array $themeRailItems = [],
		array $pluginIssueRecords = [],
		array $themeIssueRecords = [],
		array $tabAvailability = []
	) {
		$this->summaryRows = $summaryRows;
		$this->assessmentRows = $assessmentRows;
		$this->wordpressPayload = $wordpressPayload;
		$this->pluginsPayload = $pluginsPayload;
		$this->themesPayload = $themesPayload;
		$this->malwarePayload = $malwarePayload;
		$this->fileLockerPayload = $fileLockerPayload;
		$this->vulnerabilities = $vulnerabilities;
		$this->wordpressEnabled = $wordpressEnabled;
		$this->pluginsEnabled = $pluginsEnabled;
		$this->themesEnabled = $themesEnabled;
		$this->vulnerabilitiesEnabled = $vulnerabilitiesEnabled;
		$this->malwareEnabled = $malwareEnabled;
		$this->afsDisplayItems = $afsDisplayItems;
		$this->problemLocks = $problemLocks;
		$this->goodLocks = $goodLocks;
		$this->pluginRailItems = $pluginRailItems;
		$this->themeRailItems = $themeRailItems;
		$this->pluginIssueRecords = $pluginIssueRecords;
		$this->themeIssueRecords = $themeIssueRecords;
		$this->tabAvailability = $tabAvailability;
	}

	protected function buildSummaryRows() :array {
		return $this->normalizeSummaryRows( $this->summaryRows );
	}

	protected function buildAssessmentRows() :array {
		return $this->normalizeAssessmentRows( $this->assessmentRows );
	}

	protected function buildWordpressSectionPayload() :array {
		return $this->wordpressPayload;
	}

	protected function buildPluginsSectionPayload() :array {
		return $this->pluginsPayload;
	}

	protected function buildThemesSectionPayload() :array {
		return $this->themesPayload;
	}

	protected function buildMalwareSectionPayload() :array {
		return $this->malwarePayload;
	}

	protected function buildFileLockerSectionPayload() :array {
		return $this->fileLockerPayload;
	}

	protected function buildVulnerabilities() :array {
		return $this->normalizeVulnerabilities( $this->vulnerabilities );
	}

	protected function isWordpressTabEnabled() :bool {
		return $this->wordpressEnabled;
	}

	protected function isPluginsRailTabEnabled() :bool {
		return $this->pluginsEnabled;
	}

	protected function isThemesRailTabEnabled() :bool {
		return $this->themesEnabled;
	}

	protected function isVulnerabilitiesRailTabEnabled() :bool {
		return $this->vulnerabilitiesEnabled;
	}

	protected function isMalwareRailTabEnabled() :bool {
		return $this->malwareEnabled;
	}

	protected function getAfsDisplayItems() :array {
		return $this->afsDisplayItems;
	}

	protected function getProblemFileLocks() :array {
		return $this->problemLocks;
	}

	protected function getGoodFileLocks() :array {
		return $this->goodLocks;
	}

	protected function isFileLockerEnabled() :bool {
		return true;
	}

	protected function isPremiumActive() :bool {
		return true;
	}

	protected function buildPluginThemeRailItemsDirect( string $assetType ) :array {
		$items = $assetType === 'plugin' ? $this->pluginRailItems : $this->themeRailItems;
		return !empty( $items ) ? $items : parent::buildPluginThemeRailItemsDirect( $assetType );
	}

	protected function buildPluginThemeIssueRecords( string $assetType ) :array {
		return $assetType === 'plugin' ? $this->pluginIssueRecords : $this->themeIssueRecords;
	}

	protected function getRailTabAvailability( string $tabKey ) :array {
		if ( isset( $this->tabAvailability[ $tabKey ] ) ) {
			return $this->tabAvailability[ $tabKey ];
		}

		$isAvailable = match ( $tabKey ) {
			'wordpress' => $this->wordpressEnabled,
			'plugins' => $this->pluginsEnabled,
			'themes' => $this->themesEnabled,
			'vulnerabilities' => $this->vulnerabilitiesEnabled,
			'malware' => $this->malwareEnabled,
			'file_locker' => true,
			default => false,
		};

		return [
			'is_available' => $isAvailable,
			'show_in_actions_queue' => \in_array( $tabKey, [ 'plugins', 'themes', 'vulnerabilities', 'malware', 'file_locker' ], true )
				|| ( $tabKey === 'wordpress' && $this->wordpressEnabled ),
			'disabled_message' => '',
			'disabled_status' => 'neutral',
		];
	}

	protected function buildAjaxRenderActionData( string $actionClass, array $aux = [] ) :array {
		$slug = $actionClass === \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansFileLockerDiff::class
			? 'filelocker_showdiff'
			: 'render_action';

		return \array_merge( [
			'render_slug' => $slug,
		], $aux );
	}
}
