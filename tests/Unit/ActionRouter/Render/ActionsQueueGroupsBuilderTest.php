<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	Malware,
	Vulnerabilities
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueGroupsBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActionsQueueGroupsBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		Functions\when( 'number_format_i18n' )->alias( static fn( int $number ) :string => (string)$number );
	}

	public function test_build_expands_scan_bucket_into_per_asset_and_linked_groups() :void {
		$builder = $this->createBuilder(
			[
				$this->makeQueueAssetCard( 'example-plugin', 'Example Plugin', 3, 'plugin', 'example-plugin/example-plugin.php' ),
			],
			[
				$this->makeQueueAssetCard( 'example-theme', 'Example Theme', 1, 'theme', 'example-theme' ),
			],
			[
				'count'    => 1,
				'status'   => 'critical',
				'sections' => [
					'vulnerable' => [
						'label' => 'Known Vulnerabilities',
						'items' => [
							[
								'key'         => 'vulnerability-example-plugin',
								'asset_key'   => 'example-plugin',
								'label'       => 'Example Plugin',
								'description' => '1 known vulnerability needs review.',
								'count'       => 1,
								'severity'    => 'critical',
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
					'abandoned'  => [
						'label' => 'Abandoned Assets',
						'items' => [],
					],
				],
			]
		);

		$data = $builder->build(
			'critical',
			[
				'items' => [
					[
						'key'      => 'plugin_files',
						'count'    => 3,
						'severity' => 'critical',
						'zone'     => 'scans',
					],
					[
						'key'      => 'theme_files',
						'count'    => 1,
						'severity' => 'critical',
						'zone'     => 'scans',
					],
					[
						'key'      => 'vulnerable_assets',
						'count'    => 1,
						'severity' => 'critical',
						'zone'     => 'scans',
					],
					[
						'key'      => 'malware',
						'count'    => 2,
						'severity' => 'critical',
						'zone'     => 'scans',
					],
				],
			],
			[
				'scans'       => [],
				'maintenance' => [],
			]
		);

		$groups = $this->flattenLayerGroups( $data );

		$this->assertSame( 'Fix now', $data[ 'bucket_selection' ][ 'label' ] );
		$this->assertSame( 'critical', $data[ 'bucket_selection' ][ 'status' ] );
		$this->assertSame( 7, $data[ 'bucket_selection' ][ 'item_count' ] );
		$this->assertSame(
			[ 'vulnerabilities:vulnerability-example-plugin', 'plugins:example-plugin', 'themes:example-theme', 'malware' ],
			\array_column( $groups, 'key' )
		);
		$this->assertSame( [ 'linked', 'expandable', 'expandable', 'expandable' ], \array_column( $groups, 'card_type' ) );
		$this->assertSame( [ 'Known Vulnerabilities', 'Plugin Files', 'Theme Files', '' ], \array_column( $data[ 'active_sections' ], 'heading_label' ) );
		$this->assertSame( Vulnerabilities::class, $groups[ 0 ][ 'render_action_class' ] );
		$this->assertSame( Malware::class, $groups[ 3 ][ 'render_action_class' ] );
		$this->assertSame(
			[
				'display_context'         => 'actions_queue',
				'results_display_options' => [
					'include_ignored' => false,
					'ignored_only'    => false,
				],
			],
			$groups[ 3 ][ 'render_action_data' ]
		);
		$this->assertSame(
			[
				[
					'label'      => 'Go to updates',
					'href'       => '/wp-admin/update-core.php',
					'target'     => '',
					'rel'        => '',
					'icon_class' => '',
				],
				[
					'label'      => 'Vulnerability Lookup',
					'href'       => 'https://lookup.example/plugin',
					'target'     => '_blank',
					'rel'        => 'noopener noreferrer',
					'icon_class' => 'bi-box-arrow-up-right',
				],
			],
			$groups[ 0 ][ 'links' ]
		);
		$this->assertSame( 'direct_table', $groups[ 1 ][ 'detail_shell' ] );
		$this->assertSame( 'file_scan_results', $groups[ 1 ][ 'detail_table' ][ 'table_type' ] );
		$this->assertSame( 'example-plugin/example-plugin.php', $groups[ 1 ][ 'detail_table' ][ 'subject_id' ] );
		$this->assertSame( 'View 3 files', $groups[ 1 ][ 'drill_hint' ] );
		$this->assertSame( '2 suspected malware results need review.', $groups[ 3 ][ 'narrative' ] );
		$this->assertSame( 'View 2 files', $groups[ 3 ][ 'drill_hint' ] );
	}

	public function test_build_group_returns_selected_plugin_asset_with_direct_table_detail() :void {
		$builder = $this->createBuilder(
			[
				$this->makeQueueAssetCard( 'example-plugin', 'Example Plugin', 3, 'plugin', 'example-plugin/example-plugin.php' ),
			]
		);

		$group = $builder->buildGroup(
			'critical',
			'plugins:example-plugin',
			[
				'items' => [
					[
						'key'      => 'plugin_files',
						'count'    => 3,
						'severity' => 'critical',
						'zone'     => 'scans',
					],
				],
			],
			[
				'scans'       => [],
				'maintenance' => [],
			]
		);

		$this->assertSame( 'plugins:example-plugin', $group[ 'key' ] );
		$this->assertSame( 'Example Plugin', $group[ 'label' ] );
		$this->assertSame( 'direct_table', $group[ 'detail_shell' ] );
		$this->assertSame( 'expandable', $group[ 'card_type' ] );
		$this->assertSame( 'View 3 files', $group[ 'drill_hint' ] );
		$this->assertSame( 'example-plugin/example-plugin.php', $group[ 'detail_table' ][ 'subject_id' ] );
		$this->assertSame( 'plugins:example-plugin', $group[ 'selection' ][ 'key' ] );
		$this->assertSame( 'direct_table', $group[ 'selection' ][ 'detail_shell' ] );
	}

	public function test_build_review_bucket_splits_maintenance_into_per_key_category_cards() :void {
		$builder = $this->createBuilder(
			[],
			[],
			[],
			[
				[
					'key'           => 'wp_plugins_updates',
					'zone'          => 'maintenance',
					'label'         => 'Plugin Updates',
					'icon_class'    => 'bi bi-plug-fill',
					'count'         => 1,
					'severity'      => 'warning',
					'description'   => 'There is 1 plugin update waiting to be applied.',
					'href'          => '/wp-admin/update-core.php',
					'action'        => 'Update now',
					'target'        => '',
					'cta'           => [
						'href'  => '/wp-admin/update-core.php',
						'label' => 'Manage Plugins',
					],
					'toggle_action' => [],
					'expansion'     => [
						'table' => [
							'rows' => [
								[
									'icon_class'        => 'bi bi-plug-fill',
									'inline_meta'       => 'Version 5.3.0',
									'title'             => 'Akismet Anti-Spam',
									'subtitle'          => 'Plugin update available',
									'context'           => 'Current: 5.3.0 | Available: 5.4.0',
									'identifier'        => 'akismet/akismet.php',
									'action'            => [
										'href'  => '/wp-admin/update.php?action=upgrade-plugin&plugin=akismet/akismet.php',
										'label' => 'Update',
									],
									'is_ignored'        => false,
									'ignored_label'     => '',
									'secondary_actions' => [
										[
											'label'       => 'Ignore',
											'href'        => 'javascript:{}',
											'icon'        => 'bi bi-eye-slash-fill',
											'tooltip'     => 'Ignore this maintenance item',
											'ajax_action' => [ 'ex' => 'maintenance_item_ignore' ],
										],
									],
								],
							],
						],
					],
				],
				[
					'key'           => 'wp_plugins_inactive',
					'zone'          => 'maintenance',
					'label'         => 'Inactive Plugins',
					'icon_class'    => 'bi bi-plug-fill',
					'count'         => 1,
					'severity'      => 'warning',
					'description'   => 'There is 1 unused plugin that should be uninstalled.',
					'href'          => '/wp-admin/plugins.php',
					'action'        => 'Go to plugins',
					'target'        => '',
					'cta'           => [
						'href'  => '/wp-admin/plugins.php',
						'label' => 'Manage Plugins',
					],
					'toggle_action' => [],
					'expansion'     => [
						'table' => [
							'rows' => [
								[
									'icon_class'        => 'bi bi-plug-fill',
									'inline_meta'       => 'Version 1.7.2',
									'title'             => 'Hello Dolly',
									'subtitle'          => 'Plugin is currently inactive',
									'context'           => 'Version: 1.7.2',
									'identifier'        => 'hello-dolly/hello.php',
									'action'            => [
										'href'         => '/wp-admin/plugins.php?s=hello-dolly%2Fhello.php',
										'label'        => 'Manage this plugin',
										'icon'         => 'bi bi-arrow-right-circle-fill',
										'tooltip'      => 'Manage this plugin',
										'is_icon_only' => true,
										'target'       => '_blank',
									],
									'is_ignored'        => true,
									'ignored_label'     => 'Currently ignored',
									'secondary_actions' => [
										[
											'label'       => 'Stop ignoring',
											'href'        => 'javascript:{}',
											'icon'        => 'bi bi-eye-fill',
											'tooltip'     => 'Stop ignoring this maintenance item',
											'ajax_action' => [ 'ex' => 'maintenance_item_unignore' ],
										],
									],
								],
							],
						],
					],
				],
			]
		);

		$data = $builder->build(
			'review',
			[
				'items' => [
					[
						'key'      => 'wp_plugins_updates',
						'count'    => 1,
						'severity' => 'warning',
						'zone'     => 'maintenance',
					],
					[
						'key'      => 'wp_plugins_inactive',
						'count'    => 1,
						'severity' => 'warning',
						'zone'     => 'maintenance',
					],
				],
			],
			[
				'scans'       => [],
				'maintenance' => [
					[
						'key'               => 'wp_plugins_updates',
						'label'             => 'Plugin Updates',
						'description'       => 'There is an upgrade available for a plugin.',
						'status'            => 'warning',
						'status_label'      => 'Warning',
						'status_icon_class' => 'bi bi-exclamation-circle-fill',
					],
					[
						'key'               => 'wp_plugins_inactive',
						'label'             => 'Inactive Plugins',
						'description'       => 'Unused plugins should be reviewed.',
						'status'            => 'warning',
						'status_label'      => 'Warning',
						'status_icon_class' => 'bi bi-exclamation-circle-fill',
					],
				],
			]
		);

		$groups = $this->flattenLayerGroups( $data );

		$this->assertSame( [ 'wp_plugins_inactive', 'wp_plugins_updates' ], \array_column( $groups, 'key' ) );
		$this->assertNotContains( 'maintenance', \array_column( $groups, 'key' ) );
		$this->assertSame( [ 'category', 'category' ], \array_column( $groups, 'card_type' ) );
		$this->assertSame(
			[
				'label'      => 'Manage Plugins',
				'href'       => '/wp-admin/plugins.php',
				'target'     => '',
				'rel'        => '',
				'icon_class' => 'bi-arrow-right',
			],
			$groups[ 0 ][ 'management_link' ]
		);
		$this->assertSame( 'bi bi-plug-fill', $groups[ 0 ][ 'icon_class' ] );
		$this->assertSame(
			[
				[
					'icon_class'  => 'bi bi-plug-fill',
					'title'       => 'Hello Dolly',
					'inline_meta' => 'Version 1.7.2',
					'summary'     => '',
					'badge_label' => 'Currently ignored',
					'is_ignored'  => true,
					'actions'     => [
						[
							'label'       => 'Stop ignoring',
							'href'        => 'javascript:{}',
							'icon'        => 'bi bi-eye-fill',
							'tooltip'     => 'Stop ignoring this maintenance item',
							'ajax_action' => [ 'ex' => 'maintenance_item_unignore' ],
						],
					],
				],
			],
			$groups[ 0 ][ 'maintenance_rows' ]
		);
		$this->assertSame(
			[
				'label'      => 'Manage Plugins',
				'href'       => '/wp-admin/update-core.php',
				'target'     => '',
				'rel'        => '',
				'icon_class' => 'bi-arrow-right',
			],
			$groups[ 1 ][ 'management_link' ]
		);
		$this->assertSame( 'Akismet Anti-Spam', $groups[ 1 ][ 'maintenance_rows' ][ 0 ][ 'title' ] );
		$this->assertSame( 'bi bi-plug-fill', $groups[ 1 ][ 'icon_class' ] );
		$this->assertSame( 'Version 5.3.0', $groups[ 1 ][ 'maintenance_rows' ][ 0 ][ 'inline_meta' ] );
		$this->assertSame(
			'maintenance_item_ignore',
			$groups[ 1 ][ 'maintenance_rows' ][ 0 ][ 'actions' ][ 0 ][ 'ajax_action' ][ 'ex' ]
		);
		$this->assertSame( '', $groups[ 0 ][ 'drill_hint' ] );
		$this->assertSame( 'maintenance', $groups[ 0 ][ 'detail_shell' ] );
	}

	public function test_build_review_bucket_keeps_singleton_maintenance_groups_as_header_and_copy_when_no_sub_items_exist() :void {
		$builder = $this->createBuilder(
			[],
			[],
			[],
			[
				[
					'key'           => 'system_php_version',
					'zone'          => 'maintenance',
					'label'         => 'PHP Version',
					'icon_class'    => 'bi bi-code-slash',
					'count'         => 1,
					'severity'      => 'warning',
					'description'   => 'PHP should be reviewed.',
					'href'          => '/wp-admin/site-health.php',
					'action'        => 'Open',
					'target'        => '',
					'cta'           => [],
					'toggle_action' => [],
					'expansion'     => [],
				],
			]
		);

		$data = $builder->build(
			'review',
			[
				'items' => [
					[
						'key'      => 'system_php_version',
						'count'    => 1,
						'severity' => 'warning',
						'zone'     => 'maintenance',
					],
				],
			],
			[
				'scans'       => [],
				'maintenance' => [],
			]
		);

		$groups = $this->flattenLayerGroups( $data );

		$this->assertSame( 'system_php_version', $groups[ 0 ][ 'key' ] );
		$this->assertSame( 'bi bi-code-slash', $groups[ 0 ][ 'icon_class' ] );
		$this->assertSame( [], $groups[ 0 ][ 'maintenance_rows' ] );
		$this->assertSame(
			[
				'icon_class'  => 'bi bi-code-slash',
				'title'       => '',
				'inline_meta' => '',
				'summary'     => 'PHP should be reviewed.',
				'badge_label' => '',
				'is_ignored'  => false,
				'actions'     => [],
			],
			$groups[ 0 ][ 'summary_row' ]
		);
		$this->assertSame( [], $groups[ 0 ][ 'management_link' ] );
		$this->assertSame( 'PHP should be reviewed.', $groups[ 0 ][ 'narrative' ] );
	}

	public function test_build_review_bucket_keeps_ignored_singleton_maintenance_toggle_in_summary_row() :void {
		$builder = $this->createBuilder(
			[],
			[],
			[],
			[
				[
					'key'           => 'system_php_version',
					'zone'          => 'maintenance',
					'label'         => 'PHP Version',
					'icon_class'    => 'bi bi-code-slash',
					'count'         => 0,
					'severity'      => 'good',
					'drill_bucket'  => 'review',
					'description'   => 'This maintenance item is currently ignored.',
					'href'          => '/wp-admin/site-health.php',
					'action'        => 'Open',
					'target'        => '',
					'cta'           => [],
					'toggle_action' => [
						'kind'        => 'unignore',
						'label'       => 'Stop ignoring',
						'href'        => 'javascript:{}',
						'icon'        => 'bi bi-eye-fill',
						'tooltip'     => 'Stop ignoring this maintenance item',
						'ajax_action' => [ 'ex' => 'maintenance_item_unignore' ],
					],
					'expansion'     => [],
				],
			]
		);

		$data = $builder->build(
			'review',
			[
				'items' => [],
			],
			[
				'scans'       => [],
				'maintenance' => [
					[
						'key'               => 'system_php_version',
						'label'             => 'PHP Version',
						'description'       => 'This maintenance item is currently ignored.',
						'drill_bucket'      => 'review',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-check-circle-fill',
					],
				],
			]
		);

		$healthyGroups = $this->flattenSections( $data[ 'healthy_sections' ] );

		$this->assertCount( 1, $healthyGroups );
		$this->assertSame( 'Currently ignored', $healthyGroups[ 0 ][ 'summary_row' ][ 'badge_label' ] );
		$this->assertTrue( $healthyGroups[ 0 ][ 'summary_row' ][ 'is_ignored' ] );
		$this->assertSame(
			'maintenance_item_unignore',
			$healthyGroups[ 0 ][ 'summary_row' ][ 'actions' ][ 0 ][ 'ajax_action' ][ 'ex' ]
		);
		$this->assertSame( 'unignore', $healthyGroups[ 0 ][ 'summary_row' ][ 'actions' ][ 0 ][ 'kind' ] );
	}

	public function test_build_reads_vulnerabilities_payload_once_when_expanding_both_sections() :void {
		$builder = $this->createBuilder(
			[],
			[],
			[
				'count'    => 2,
				'status'   => 'critical',
				'sections' => [
					'vulnerable' => [
						'label' => 'Known Vulnerabilities',
						'items' => [
							[
								'key'         => 'vulnerability-example-plugin',
								'asset_key'   => 'example-plugin',
								'label'       => 'Example Plugin',
								'description' => '1 known vulnerability needs review.',
								'count'       => 1,
								'severity'    => 'critical',
								'actions'     => [],
							],
						],
					],
					'abandoned'  => [
						'label' => 'Abandoned Assets',
						'items' => [
							[
								'key'         => 'abandoned-example-theme',
								'asset_key'   => 'example-theme',
								'label'       => 'Example Theme',
								'description' => 'This asset appears to be abandoned and should be reviewed.',
								'count'       => 1,
								'severity'    => 'critical',
								'actions'     => [],
							],
						],
					],
				],
			]
		);

		$data = $builder->build(
			'critical',
			[
				'items' => [
					[
						'key'      => 'vulnerable_assets',
						'count'    => 1,
						'severity' => 'critical',
						'zone'     => 'scans',
					],
					[
						'key'      => 'abandoned',
						'count'    => 1,
						'severity' => 'critical',
						'zone'     => 'scans',
					],
				],
			],
			[
				'scans'       => [],
				'maintenance' => [],
			]
		);

		$this->assertSame(
			[ 'vulnerabilities:abandoned-example-theme', 'vulnerabilities:vulnerability-example-plugin' ],
			\array_column( $this->flattenLayerGroups( $data ), 'key' )
		);
		$this->assertSame( 1, $builder->getVulnerabilitiesPayloadCalls() );
	}

	public function test_build_review_bucket_appends_healthy_groups_after_active_groups_and_keeps_selected_healthy_group_resolvable() :void {
		$builder = $this->createBuilder(
			[],
			[],
			[],
			[
				[
					'key'           => 'wp_plugins_updates',
					'zone'          => 'maintenance',
					'label'         => 'Plugin Updates',
					'icon_class'    => 'bi bi-plug-fill',
					'count'         => 1,
					'severity'      => 'warning',
					'drill_bucket'  => 'review',
					'description'   => 'There is 1 plugin update waiting to be applied.',
					'href'          => '/wp-admin/update-core.php',
					'action'        => 'Update now',
					'target'        => '',
					'cta'           => [
						'href'  => '/wp-admin/update-core.php',
						'label' => 'Manage Plugins',
					],
					'toggle_action' => [],
					'expansion'     => [
						'table' => [
							'rows' => [
								[
									'icon_class'        => 'bi bi-plug-fill',
									'inline_meta'       => 'Version 5.3.0',
									'title'             => 'Akismet Anti-Spam',
									'subtitle'          => 'Plugin update available',
									'context'           => 'Current: 5.3.0 | Available: 5.4.0',
									'identifier'        => 'akismet/akismet.php',
									'action'            => [
										'href'  => '/wp-admin/update.php?action=upgrade-plugin&plugin=akismet/akismet.php',
										'label' => 'Update',
									],
									'is_ignored'        => false,
									'ignored_label'     => '',
									'secondary_actions' => [
										[
											'label'       => 'Ignore',
											'href'        => 'javascript:{}',
											'icon'        => 'bi bi-eye-slash-fill',
											'tooltip'     => 'Ignore this maintenance item',
											'ajax_action' => [ 'ex' => 'maintenance_item_ignore' ],
										],
									],
								],
							],
						],
					],
				],
				[
					'key'           => 'wp_plugins_inactive',
					'zone'          => 'maintenance',
					'label'         => 'Inactive Plugins',
					'icon_class'    => 'bi bi-plug-fill',
					'count'         => 0,
					'severity'      => 'good',
					'drill_bucket'  => 'review',
					'description'   => '1 item is currently ignored.',
					'href'          => '/wp-admin/plugins.php',
					'action'        => 'Go to plugins',
					'target'        => '',
					'cta'           => [
						'href'  => '/wp-admin/plugins.php',
						'label' => 'Manage Plugins',
					],
					'toggle_action' => [],
					'expansion'     => [
						'table' => [
							'rows' => [
								[
									'icon_class'        => 'bi bi-plug-fill',
									'inline_meta'       => 'Version 1.7.2',
									'title'             => 'Hello Dolly',
									'subtitle'          => 'Plugin is currently inactive',
									'context'           => 'Version: 1.7.2',
									'identifier'        => 'hello-dolly/hello.php',
									'action'            => [
										'href'         => '/wp-admin/plugins.php?s=hello-dolly%2Fhello.php',
										'label'        => 'Manage this plugin',
										'icon'         => 'bi bi-arrow-right-circle-fill',
										'tooltip'      => 'Manage this plugin',
										'is_icon_only' => true,
										'target'       => '_blank',
									],
									'is_ignored'        => true,
									'ignored_label'     => 'Currently ignored',
									'secondary_actions' => [
										[
											'label'       => 'Stop ignoring',
											'href'        => 'javascript:{}',
											'icon'        => 'bi bi-eye-fill',
											'tooltip'     => 'Stop ignoring this maintenance item',
											'ajax_action' => [ 'ex' => 'maintenance_item_unignore' ],
										],
									],
								],
							],
						],
					],
				],
			]
		);

		$payload = $builder->buildWithSelectedGroup(
			'review',
			'wp_plugins_inactive',
			[
				'items' => [
					[
						'key'      => 'wp_plugins_updates',
						'count'    => 1,
						'severity' => 'warning',
						'zone'     => 'maintenance',
					],
				],
			],
			[
				'scans'       => [
					[
						'key'               => 'plugin_files',
						'label'             => 'Plugin Files',
						'description'       => 'All plugin files appear to be valid.',
						'drill_bucket'      => 'critical',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-patch-check-fill',
					],
				],
				'maintenance' => [
					[
						'key'               => 'wp_plugins_updates',
						'label'             => 'Plugin Updates',
						'description'       => 'There is an upgrade available for a plugin.',
						'drill_bucket'      => 'review',
						'status'            => 'warning',
						'status_label'      => 'Warning',
						'status_icon_class' => 'bi bi-exclamation-circle-fill',
					],
					[
						'key'               => 'wp_plugins_inactive',
						'label'             => 'Inactive Plugins',
						'description'       => 'This maintenance item is currently ignored.',
						'drill_bucket'      => 'review',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-patch-check-fill',
					],
				],
			]
		);

		$this->assertSame(
			[ 'wp_plugins_updates' ],
			\array_column( $this->flattenSections( $payload[ 'layer' ][ 'active_sections' ] ), 'key' )
		);
		$this->assertSame(
			[ 'wp_plugins_inactive' ],
			\array_column( $this->flattenSections( $payload[ 'layer' ][ 'healthy_sections' ] ), 'key' )
		);
		$this->assertSame( 'No action required', $payload[ 'layer' ][ 'healthy_heading_label' ] );
		$this->assertSame( 'good', $this->flattenSections( $payload[ 'layer' ][ 'healthy_sections' ] )[ 0 ][ 'status' ] );
		$this->assertSame( '', $this->flattenSections( $payload[ 'layer' ][ 'healthy_sections' ] )[ 0 ][ 'drill_hint' ] );
		$this->assertArrayNotHasKey( 'next_move', $payload[ 'selected_group' ] );
		$this->assertSame( 1, $payload[ 'selected_group' ][ 'item_count' ] );
		$this->assertSame(
			'maintenance_item_unignore',
			$payload[ 'selected_group' ][ 'maintenance_rows' ][ 0 ][ 'actions' ][ 0 ][ 'ajax_action' ][ 'ex' ]
		);
	}

	public function test_build_critical_bucket_keeps_healthy_vulnerabilities_static() :void {
		$builder = $this->createBuilder();

		$payload = $builder->buildWithSelectedGroup(
			'critical',
			'vulnerabilities',
			[
				'items' => [],
			],
			[
				'scans'       => [
					[
						'key'               => 'vulnerable_assets',
						'label'             => 'Known Vulnerabilities',
						'description'       => 'Previous scans did not detect any vulnerable assets.',
						'drill_bucket'      => 'critical',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-patch-check-fill',
					],
					[
						'key'               => 'abandoned',
						'label'             => 'Abandoned Assets',
						'description'       => 'Previous scans did not detect any abandoned assets.',
						'drill_bucket'      => 'critical',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-patch-check-fill',
					],
				],
				'maintenance' => [],
			]
		);

		$healthyGroups = $this->flattenSections( $payload[ 'layer' ][ 'healthy_sections' ] );
		$this->assertSame( [ 'vulnerabilities' ], \array_column( $healthyGroups, 'key' ) );
		$this->assertSame( 'linked', $healthyGroups[ 0 ][ 'card_type' ] );
		$this->assertSame( [], $healthyGroups[ 0 ][ 'links' ] );
		$this->assertFalse( $healthyGroups[ 0 ][ 'is_interactive' ] );
		$this->assertSame( [], $healthyGroups[ 0 ][ 'render_action_data' ] );
		$this->assertSame( 'vulnerabilities', $payload[ 'selected_group' ][ 'key' ] );
		$this->assertSame( 'linked', $payload[ 'selected_group' ][ 'card_type' ] );
		$this->assertFalse( $payload[ 'selected_group' ][ 'is_interactive' ] );
		$this->assertSame( '', $payload[ 'selected_group' ][ 'drill_hint' ] );
	}

	public function test_build_critical_bucket_only_makes_healthy_scan_groups_clickable_when_ignored_results_exist() :void {
		$builder = $this->createBuilder(
			[],
			[],
			[],
			[],
			[
				$this->makeQueueAssetCard( 'ignored-plugin', 'Ignored Plugin', 2, 'plugin', 'ignored-plugin/ignored-plugin.php' ),
			],
			[],
			3
		);

		$data = $builder->build(
			'critical',
			[
				'items' => [],
			],
			[
				'scans'       => [
					[
						'key'               => 'wp_files',
						'label'             => 'WordPress Files',
						'description'       => 'All WordPress core files appear to be valid.',
						'drill_bucket'      => 'critical',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-patch-check-fill',
					],
					[
						'key'               => 'plugin_files',
						'label'             => 'Plugin Files',
						'description'       => 'All plugin files appear to be valid.',
						'drill_bucket'      => 'critical',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-patch-check-fill',
					],
					[
						'key'               => 'theme_files',
						'label'             => 'Theme Files',
						'description'       => 'All theme files appear to be valid.',
						'drill_bucket'      => 'critical',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-patch-check-fill',
					],
				],
				'maintenance' => [],
			]
		);

		$groups = [];
		foreach ( $this->flattenSections( $data[ 'healthy_sections' ] ) as $group ) {
			$groups[ $group[ 'key' ] ] = $group;
		}

		$this->assertTrue( $groups[ 'wordpress' ][ 'is_interactive' ] );
		$this->assertSame(
			[
				'display_context'         => 'actions_queue',
				'results_display_options' => [
					'include_ignored' => true,
					'ignored_only'    => true,
				],
			],
			$groups[ 'wordpress' ][ 'render_action_data' ]
		);
		$this->assertSame( 3, $groups[ 'wordpress' ][ 'item_count' ] );
		$this->assertTrue( $groups[ 'plugins' ][ 'is_interactive' ] );
		$this->assertSame( 2, $groups[ 'plugins' ][ 'item_count' ] );
		$this->assertFalse( $groups[ 'themes' ][ 'is_interactive' ] );
		$this->assertSame( [], $groups[ 'themes' ][ 'render_action_data' ] );
		$this->assertSame( [ '', '', '' ], \array_column( $data[ 'healthy_sections' ], 'heading_label' ) );
	}

	public function test_build_reuses_shared_active_and_ignored_pane_options_for_scan_groups() :void {
		$builder = $this->createBuilder(
			[
				$this->makeQueueAssetCard( 'example-plugin', 'Example Plugin', 3, 'plugin', 'example-plugin/example-plugin.php' ),
			],
			[
				$this->makeQueueAssetCard( 'example-theme', 'Example Theme', 1, 'theme', 'example-theme' ),
			],
			[],
			[],
			[
				$this->makeQueueAssetCard( 'ignored-plugin', 'Ignored Plugin', 2, 'plugin', 'ignored-plugin/ignored-plugin.php' ),
			],
			[
				$this->makeQueueAssetCard( 'ignored-theme', 'Ignored Theme', 4, 'theme', 'ignored-theme' ),
			]
		);

		$builder->build(
			'critical',
			[
				'items' => [
					[
						'key'      => 'plugin_files',
						'count'    => 3,
						'severity' => 'critical',
						'zone'     => 'scans',
					],
					[
						'key'      => 'theme_files',
						'count'    => 1,
						'severity' => 'critical',
						'zone'     => 'scans',
					],
				],
			],
			[
				'scans'       => [
					[
						'key'               => 'plugin_files',
						'label'             => 'Plugin Files',
						'description'       => 'All plugin files appear to be valid.',
						'drill_bucket'      => 'critical',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-patch-check-fill',
					],
					[
						'key'               => 'theme_files',
						'label'             => 'Theme Files',
						'description'       => 'All theme files appear to be valid.',
						'drill_bucket'      => 'critical',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-patch-check-fill',
					],
				],
				'maintenance' => [],
			]
		);

		$this->assertSame(
			[
				[ 'include_ignored' => false, 'ignored_only' => false ],
				[ 'include_ignored' => true, 'ignored_only' => true ],
			],
			$builder->getPluginPaneCalls()
		);
		$this->assertSame(
			[
				[ 'include_ignored' => false, 'ignored_only' => false ],
				[ 'include_ignored' => true, 'ignored_only' => true ],
			],
			$builder->getThemePaneCalls()
		);
	}

	/**
	 * @param list<array{heading_label:string,groups:list<array<string,mixed>>}> $sections
	 * @return list<array<string,mixed>>
	 */
	private function flattenSections( array $sections ) :array {
		return \array_merge( [], ...\array_map(
			static fn( array $section ) :array => $section[ 'groups' ],
			$sections
		) );
	}

	/**
	 * @param array{active_sections:list<array{heading_label:string,groups:list<array<string,mixed>>}>,healthy_sections:list<array{heading_label:string,groups:list<array<string,mixed>>}>} $layer
	 * @return list<array<string,mixed>>
	 */
	private function flattenLayerGroups( array $layer ) :array {
		return \array_merge(
			$this->flattenSections( $layer[ 'active_sections' ] ),
			$this->flattenSections( $layer[ 'healthy_sections' ] )
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function makeQueueAssetCard( string $key, string $title, int $count, string $subjectType, string $subjectId ) :array {
		return [
			'key'               => $key,
			'panel_id'          => 'actions-queue-'.$subjectType.'-card-'.$key,
			'panel_target'      => 'actions-queue-'.$subjectType.'-'.$key,
			'expand_target'     => 'scan-files-'.$subjectType.'-'.$key,
			'status'            => 'warning',
			'icon_class'        => $subjectType === 'plugin' ? 'bi bi-plug-fill' : 'bi bi-palette-fill',
			'title'             => $title,
			'stat_text'         => \sprintf( $count === 1 ? '%s file needs review' : '%s files need review', $count ),
			'meta_text'         => $subjectId,
			'show_meta_in_tile' => true,
			'count_badge'       => $count,
			'actions'           => [],
			'table'             => [
				'table_type'  => 'file_scan_results',
				'subject_type' => $subjectType,
				'subject_id'  => $subjectId,
			],
			'render_action'     => [],
		];
	}

	private function createBuilder(
		array $pluginCards = [],
		array $themeCards = [],
		array $vulnerabilities = [],
		array $maintenanceItems = [],
		array $ignoredPluginCards = [],
		array $ignoredThemeCards = [],
		int $ignoredWordpressCount = 0
	) :ActionsQueueGroupsBuilder {
		return new class(
			$pluginCards,
			$themeCards,
			$vulnerabilities,
			$maintenanceItems,
			$ignoredPluginCards,
			$ignoredThemeCards,
			$ignoredWordpressCount
		) extends ActionsQueueGroupsBuilder {

			private int $vulnerabilitiesPayloadCalls = 0;
			private array $pluginPaneCalls = [];
			private array $themePaneCalls = [];

			public function __construct(
				private array $pluginCards,
				private array $themeCards,
				private array $vulnerabilities,
				private array $maintenanceItems,
				private array $ignoredPluginCards,
				private array $ignoredThemeCards,
				private int $ignoredWordpressCount
			) {
			}

			protected function buildActionsQueuePluginsPane( array $resultsDisplayOptions = [] ) :array {
				$this->pluginPaneCalls[] = $resultsDisplayOptions;
				return [
					'is_disabled'      => false,
					'disabled_message' => '',
					'cards'            => !empty( $resultsDisplayOptions[ 'ignored_only' ] )
						? $this->ignoredPluginCards
						: $this->pluginCards,
				];
			}

			protected function buildActionsQueueThemesPane( array $resultsDisplayOptions = [] ) :array {
				$this->themePaneCalls[] = $resultsDisplayOptions;
				return [
					'is_disabled'      => false,
					'disabled_message' => '',
					'cards'            => !empty( $resultsDisplayOptions[ 'ignored_only' ] )
						? $this->ignoredThemeCards
						: $this->themeCards,
				];
			}

			protected function buildVulnerabilitiesPayload() :array {
				$this->vulnerabilitiesPayloadCalls++;
				return $this->vulnerabilities !== []
					? $this->vulnerabilities
					: [
						'count'    => 0,
						'status'   => 'good',
						'sections' => [
							'vulnerable' => [
								'label' => 'Known Vulnerabilities',
								'items' => [],
							],
							'abandoned'  => [
								'label' => 'Abandoned Assets',
								'items' => [],
							],
						],
					];
			}

			public function getVulnerabilitiesPayloadCalls() :int {
				return $this->vulnerabilitiesPayloadCalls;
			}

			public function getPluginPaneCalls() :array {
				return $this->pluginPaneCalls;
			}

			public function getThemePaneCalls() :array {
				return $this->themePaneCalls;
			}

			protected function normalizeMaintenanceQueueItems( array $items ) :array {
				return $this->maintenanceItems;
			}

			protected function normalizeBucketMaintenanceQueueItems( array $items, string $bucketKey ) :array {
				return $this->maintenanceItems;
			}

			protected function getIgnoredWordpressCount() :int {
				return $this->ignoredWordpressCount;
			}
		};
	}
}
