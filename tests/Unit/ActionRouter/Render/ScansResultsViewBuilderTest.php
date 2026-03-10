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

	public function test_build_for_actions_queue_exposes_lazy_heavy_tabs_and_eager_summary_data() :void {
		$builder = $this->createBuilder( [
			'summaryRows' => [
				[ 'key' => 'wp_files', 'label' => 'WP Files', 'count' => 2 ],
			],
			'assessmentRows' => [
				[ 'key' => 'wp_files', 'label' => 'WordPress Core Files', 'status' => 'warning', 'description' => 'Needs review' ],
			],
			'vulnerabilities' => [
				'count'    => 1,
				'status'   => 'warning',
				'sections' => [
					'vulnerable' => [
						'label' => 'Known Vulnerabilities',
						'items' => [
							[
								'label'       => 'Vulnerable Plugin',
								'description' => 'Needs update.',
								'severity'    => 'warning',
								'count'       => 1,
							],
						],
					],
				],
			],
			'wordpressEnabled'       => true,
			'pluginsEnabled'         => true,
			'themesEnabled'          => true,
			'vulnerabilitiesEnabled' => true,
			'malwareEnabled'         => true,
			'afsDisplayItems'        => [
				(object)[ 'item_id' => 'wp-file.php', 'is_in_core' => 1 ],
				(object)[ 'item_id' => 'plugin-file.php', 'is_in_plugin' => 1, 'ptg_slug' => 'plugin-one/plugin.php' ],
				(object)[ 'item_id' => 'plugin-file-2.php', 'is_in_plugin' => 1, 'ptg_slug' => 'plugin-one/plugin.php' ],
				(object)[ 'item_id' => 'theme-file.php', 'is_in_theme' => 1, 'ptg_slug' => 'theme-one' ],
				(object)[ 'item_id' => 'malware-file.php', 'malware' => 1 ],
			],
			'problemFileLocks'       => [
				(object)[ 'path' => '/locked.php', 'detected_at' => 1, 'hash_current' => '' ],
			],
		] );

		$renderData = $builder->buildForActionsQueue();
		$rail = $renderData[ 'vars' ][ 'rail' ] ?? [];
		$railTabs = $renderData[ 'vars' ][ 'rail_tabs' ] ?? [];
		$summaryTab = $this->findTabByKey( $railTabs, 'summary' );
		$wordpressTab = $this->findTabByKey( $railTabs, 'wordpress' );
		$pluginsTab = $this->findTabByKey( $railTabs, 'plugins' );
		$vulnerabilitiesTab = $this->findTabByKey( $railTabs, 'vulnerabilities' );
		$fileLockerTab = $this->findTabByKey( $railTabs, 'file_locker' );

		$this->assertArrayNotHasKey( 'tabs', $renderData[ 'vars' ] ?? [] );
		$this->assertSame( \array_column( $railTabs, 'key' ), \array_column( $rail[ 'items' ] ?? [], 'key' ) );
		$this->assertNotEmpty( $summaryTab[ 'items' ] ?? [] );
		$this->assertTrue( (bool)( $summaryTab[ 'is_loaded' ] ?? false ) );
		$this->assertFalse( (bool)( $wordpressTab[ 'is_loaded' ] ?? true ) );
		$this->assertSame( 1, $pluginsTab[ 'count' ] ?? 0 );
		$this->assertSame( 'scanresults_wordpress', $wordpressTab[ 'render_action' ][ 'render_slug' ] ?? '' );
		$this->assertNotEmpty( $vulnerabilitiesTab[ 'items' ] ?? [] );
		$this->assertTrue( (bool)( $vulnerabilitiesTab[ 'is_loaded' ] ?? false ) );
		$this->assertFalse( (bool)( $fileLockerTab[ 'is_loaded' ] ?? true ) );
		$this->assertSame( '', $renderData[ 'content' ][ 'section' ][ 'wordpress' ] ?? 'unexpected' );
	}

	public function test_build_for_actions_queue_marks_summary_status_from_lazy_tab_metadata() :void {
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
								'label'       => 'Vulnerable Plugin',
								'description' => 'Needs update.',
								'severity'    => 'critical',
								'count'       => 1,
							],
						],
					],
				],
			],
		] );

		$railTabs = $builder->buildForActionsQueue()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$summaryTab = $this->findTabByKey( $railTabs, 'summary' );

		$this->assertSame( 'critical', $summaryTab[ 'status' ] ?? '' );
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
		// No section labels when all assessments (no mixed mode)
		$withSectionLabel = \array_filter( $items, static fn( array $item ) => isset( $item[ 'section_label' ] ) );
		$this->assertEmpty( $withSectionLabel, 'Assessment-only items should not have section labels' );
	}

	// ── WordPress pane: good fallback ──

	public function test_wordpress_pane_shows_good_fallback_when_no_core_issues() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled' => true,
			'afsDisplayItems'  => [],
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$wpTab = $this->findTabByKey( $railTabs, 'wordpress' );
		$items = $wpTab[ 'items' ] ?? [];

		$this->assertCount( 1, $items );
		$this->assertSame( 'good', $items[ 0 ][ 'status' ] ?? '' );
		$this->assertStringContainsString( '6.7', $items[ 0 ][ 'title' ] ?? '' );
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

	public function test_malware_pane_shows_good_fallback_when_no_threats() :void {
		$builder = $this->createBuilder( [
			'malwareEnabled'  => true,
			'afsDisplayItems' => [],
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$malwareTab = $this->findTabByKey( $railTabs, 'malware' );
		$items = $malwareTab[ 'items' ] ?? [];

		$this->assertCount( 1, $items );
		$this->assertSame( 'good', $items[ 0 ][ 'status' ] ?? '' );
	}

	// ── File locker pane: mixed sections ──

	public function test_file_locker_shows_problem_and_good_locks_with_section_labels() :void {
		$problemLock = (object)[ 'path' => '/wp-config.php', 'detected_at' => 1000, 'hash_current' => '' ];
		$goodLock = (object)[ 'path' => '/.htaccess', 'detected_at' => 0, 'hash_current' => 'abc123' ];

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
			$this->makeDetailRow( 'Good Plugin', 'good' ),
		];

		$builder = $this->createBuilder( [
			'pluginsEnabled'   => true,
			'pluginRailItems'  => $items,
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$pluginsTab = $this->findTabByKey( $railTabs, 'plugins' );

		$this->assertCount( 2, $pluginsTab[ 'items' ] ?? [] );
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

	public function test_actions_queue_and_full_rail_share_the_same_canonical_tab_order() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled'       => true,
			'pluginsEnabled'         => true,
			'themesEnabled'          => true,
			'vulnerabilitiesEnabled' => true,
			'malwareEnabled'         => true,
			'vulnerabilities'        => [
				'count'    => 1,
				'status'   => 'warning',
				'sections' => [],
			],
		] );

		$fullRailTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$queueRailTabs = $builder->buildForActionsQueue()[ 'vars' ][ 'rail_tabs' ] ?? [];

		$this->assertSame( \array_column( $fullRailTabs, 'key' ), \array_column( $queueRailTabs, 'key' ) );
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

	// ── Summary rail-switch actions (Task 6) ──

	public function test_summary_items_with_known_keys_get_rail_switch_action_attributes() :void {
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
			$actions = $item[ 'actions' ] ?? [];
			$this->assertNotEmpty( $actions, 'Item "'.$item[ 'title' ].'" should have actions' );
			$action = $actions[ 0 ];
			$this->assertSame( 'navigate', $action[ 'type' ] ?? '' );
			$this->assertNotEmpty( $action[ 'attributes' ][ 'data-shield-rail-switch' ] ?? '' );
		}
	}

	public function test_summary_rail_switch_attributes_map_to_correct_tabs() :void {
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
			$actions = $item[ 'actions' ] ?? [];
			if ( !empty( $actions ) && isset( $actions[ 0 ][ 'attributes' ][ 'data-shield-rail-switch' ] ) ) {
				$targets[ $item[ 'title' ] ] = $actions[ 0 ][ 'attributes' ][ 'data-shield-rail-switch' ];
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
			'explanations' => [],
			'show_gear'    => false,
			'actions'      => [],
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
			$overrides[ 'themeRailItems' ] ?? []
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
		array $themeRailItems = []
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
	}

	protected function buildSummaryRows() :array {
		return $this->summaryRows;
	}

	protected function buildAssessmentRows() :array {
		return $this->assessmentRows;
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

	protected function getActionsQueueDisplayCounts() :array {
		return [
			'wordpress' => \count( \array_filter(
				$this->afsDisplayItems,
				static fn( object $item ) :bool => !empty( $item->is_in_core )
			) ),
			'malware'   => \count( \array_filter(
				$this->afsDisplayItems,
				static fn( object $item ) :bool => !empty( $item->is_mal ) || !empty( $item->malware )
			) ),
		];
	}

	protected function countAffectedAssetGroups( string $assetType ) :int {
		return \count( \array_unique( \array_filter( \array_map(
			static fn( object $item ) :string => (
				$assetType === 'plugin'
				&& !empty( $item->is_in_plugin )
			) || (
				$assetType === 'theme'
				&& !empty( $item->is_in_theme )
			)
				? (string)( $item->ptg_slug ?? '' )
				: '',
			$this->afsDisplayItems
		) ) ) );
	}

	protected function buildFileLockerSectionPayload() :array {
		return $this->fileLockerPayload;
	}

	protected function buildAjaxRenderActionData( string $actionClass ) :array {
		$map = [
			'Wordpress' => 'scanresults_wordpress',
			'Plugins'   => 'scanresults_plugins',
			'Themes'    => 'scanresults_themes',
			'Malware'   => 'scanresults_malware',
			'FileLocker'=> 'scanresults_filelocker',
		];
		$parts = \explode( '\\', $actionClass );
		$actionName = \end( $parts ) ?: '';
		return [
			'render_slug' => $map[ $actionName ] ?? '',
		];
	}

	protected function buildVulnerabilities() :array {
		return $this->vulnerabilities;
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

	protected function buildPluginThemeRailItemsDirect( string $assetType ) :array {
		return $assetType === 'plugin' ? $this->pluginRailItems : $this->themeRailItems;
	}
}
