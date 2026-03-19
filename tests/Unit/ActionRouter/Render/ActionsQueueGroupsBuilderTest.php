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

		$this->assertSame( 'Fix now', $data[ 'bucket_selection' ][ 'label' ] );
		$this->assertSame( 'critical', $data[ 'bucket_selection' ][ 'status' ] );
		$this->assertSame( 7, $data[ 'bucket_selection' ][ 'item_count' ] );
		$this->assertSame(
			[ 'vulnerabilities:vulnerability-example-plugin', 'plugins:example-plugin', 'themes:example-theme', 'malware' ],
			\array_column( $data[ 'groups' ], 'key' )
		);
		$this->assertSame( [ 'linked', 'expandable', 'expandable', 'expandable' ], \array_column( $data[ 'groups' ], 'card_type' ) );
		$this->assertSame( [ 'Known Vulnerabilities', 'Plugin Files', 'Theme Files', 'Malware Detections' ], \array_column( $data[ 'groups' ], 'heading_label' ) );
		$this->assertSame( Vulnerabilities::class, $data[ 'groups' ][ 0 ][ 'render_action_class' ] );
		$this->assertSame( Malware::class, $data[ 'groups' ][ 3 ][ 'render_action_class' ] );
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
			$data[ 'groups' ][ 0 ][ 'links' ]
		);
		$this->assertSame( 'direct_table', $data[ 'groups' ][ 1 ][ 'detail_shell' ] );
		$this->assertSame( 'file_scan_results', $data[ 'groups' ][ 1 ][ 'detail_table' ][ 'table_type' ] );
		$this->assertSame( 'example-plugin/example-plugin.php', $data[ 'groups' ][ 1 ][ 'detail_table' ][ 'subject_id' ] );
		$this->assertSame( 'View 3 files', $data[ 'groups' ][ 1 ][ 'drill_hint' ] );
		$this->assertSame( '2 suspected malware results need review.', $data[ 'groups' ][ 3 ][ 'narrative' ] );
		$this->assertSame( 'View 2 files', $data[ 'groups' ][ 3 ][ 'drill_hint' ] );
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
		$this->assertSame( 'Plugin Files', $group[ 'heading_label' ] );
		$this->assertSame( 'Example Plugin', $group[ 'label' ] );
		$this->assertSame( 'direct_table', $group[ 'detail_shell' ] );
		$this->assertSame( 'expandable', $group[ 'card_type' ] );
		$this->assertSame( 'View 3 files', $group[ 'drill_hint' ] );
		$this->assertSame( 'example-plugin/example-plugin.php', $group[ 'detail_table' ][ 'subject_id' ] );
		$this->assertSame(
			[ 'Triage buckets', 'Fix now', 'Plugin Files', 'Example Plugin' ],
			$group[ 'context' ][ 'path' ]
		);
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

		$this->assertSame( [ 'wp_plugins_inactive', 'wp_plugins_updates' ], \array_column( $data[ 'groups' ], 'key' ) );
		$this->assertNotContains( 'maintenance', \array_column( $data[ 'groups' ], 'key' ) );
		$this->assertSame( [ 'category', 'category' ], \array_column( $data[ 'groups' ], 'card_type' ) );
		$this->assertSame(
			[
				'label'      => 'Manage Plugins',
				'href'       => '/wp-admin/plugins.php',
				'target'     => '',
				'rel'        => '',
				'icon_class' => 'bi-arrow-right',
			],
			$data[ 'groups' ][ 0 ][ 'management_link' ]
		);
		$this->assertSame(
			[
				[
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
			$data[ 'groups' ][ 0 ][ 'maintenance_rows' ]
		);
		$this->assertSame(
			[
				'label'      => 'Manage Plugins',
				'href'       => '/wp-admin/update-core.php',
				'target'     => '',
				'rel'        => '',
				'icon_class' => 'bi-arrow-right',
			],
			$data[ 'groups' ][ 1 ][ 'management_link' ]
		);
		$this->assertSame( 'Akismet Anti-Spam', $data[ 'groups' ][ 1 ][ 'maintenance_rows' ][ 0 ][ 'title' ] );
		$this->assertSame(
			'maintenance_item_ignore',
			$data[ 'groups' ][ 1 ][ 'maintenance_rows' ][ 0 ][ 'secondary_actions' ][ 0 ][ 'ajax_action' ][ 'ex' ]
		);
		$this->assertSame( '', $data[ 'groups' ][ 0 ][ 'drill_hint' ] );
		$this->assertSame( 'maintenance', $data[ 'groups' ][ 0 ][ 'detail_shell' ] );
	}

	public function test_build_review_bucket_keeps_singleton_maintenance_groups_on_fallback_row_when_no_sub_items_exist() :void {
		$builder = $this->createBuilder(
			[],
			[],
			[],
			[
				[
					'key'           => 'system_php_version',
					'zone'          => 'maintenance',
					'label'         => 'PHP Version',
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

		$this->assertSame( 'system_php_version', $data[ 'groups' ][ 0 ][ 'key' ] );
		$this->assertSame( [], $data[ 'groups' ][ 0 ][ 'maintenance_rows' ] );
		$this->assertSame( [], $data[ 'groups' ][ 0 ][ 'management_link' ] );
		$this->assertSame( 'PHP should be reviewed.', $data[ 'groups' ][ 0 ][ 'narrative' ] );
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
			\array_column( $data[ 'groups' ], 'key' )
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
						'drill_bucket'      => 'review',
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
			[ 'wp_plugins_updates', 'plugins', 'wp_plugins_inactive' ],
			\array_column( $payload[ 'layer' ][ 'groups' ], 'key' )
		);
		$this->assertSame(
			[ 'active', 'healthy', 'healthy' ],
			\array_column( $payload[ 'layer' ][ 'groups' ], 'display_section' )
		);
		$this->assertSame( 'good', $payload[ 'layer' ][ 'groups' ][ 1 ][ 'status' ] );
		$this->assertSame( '', $payload[ 'layer' ][ 'groups' ][ 1 ][ 'drill_hint' ] );
		$this->assertSame(
			'This maintenance group is currently looking good. Open it here any time to review or stop ignoring items.',
			$payload[ 'selected_group' ][ 'next_move' ]
		);
		$this->assertSame( 1, $payload[ 'selected_group' ][ 'item_count' ] );
		$this->assertSame(
			'maintenance_item_unignore',
			$payload[ 'selected_group' ][ 'maintenance_rows' ][ 0 ][ 'secondary_actions' ][ 0 ][ 'ajax_action' ][ 'ex' ]
		);
	}

	public function test_build_review_bucket_makes_healthy_vulnerabilities_group_drillable_without_changing_canonical_definition() :void {
		$builder = $this->createBuilder();

		$payload = $builder->buildWithSelectedGroup(
			'review',
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
						'drill_bucket'      => 'review',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-patch-check-fill',
					],
					[
						'key'               => 'abandoned',
						'label'             => 'Abandoned Assets',
						'description'       => 'Previous scans did not detect any abandoned assets.',
						'drill_bucket'      => 'review',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-patch-check-fill',
					],
				],
				'maintenance' => [],
			]
		);

		$this->assertSame( [ 'vulnerabilities' ], \array_column( $payload[ 'layer' ][ 'groups' ], 'key' ) );
		$this->assertSame( 'healthy', $payload[ 'layer' ][ 'groups' ][ 0 ][ 'display_section' ] );
		$this->assertSame( 'expandable', $payload[ 'layer' ][ 'groups' ][ 0 ][ 'card_type' ] );
		$this->assertSame( [], $payload[ 'layer' ][ 'groups' ][ 0 ][ 'links' ] );
		$this->assertSame(
			\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\Vulnerabilities::class,
			$payload[ 'layer' ][ 'groups' ][ 0 ][ 'render_action_class' ]
		);
		$this->assertSame( 'vulnerabilities', $payload[ 'selected_group' ][ 'key' ] );
		$this->assertSame( 'expandable', $payload[ 'selected_group' ][ 'card_type' ] );
		$this->assertSame( '', $payload[ 'selected_group' ][ 'drill_hint' ] );
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
		array $maintenanceItems = []
	) :ActionsQueueGroupsBuilder {
		return new class( $pluginCards, $themeCards, $vulnerabilities, $maintenanceItems ) extends ActionsQueueGroupsBuilder {

			private int $vulnerabilitiesPayloadCalls = 0;

			public function __construct(
				private array $pluginCards,
				private array $themeCards,
				private array $vulnerabilities,
				private array $maintenanceItems
			) {
			}

			protected function buildActionsQueuePluginsPane() :array {
				return [
					'is_disabled'      => false,
					'disabled_message' => '',
					'cards'            => $this->pluginCards,
				];
			}

			protected function buildActionsQueueThemesPane() :array {
				return [
					'is_disabled'      => false,
					'disabled_message' => '',
					'cards'            => $this->themeCards,
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

			protected function normalizeMaintenanceQueueItems( array $items ) :array {
				return $this->maintenanceItems;
			}

			protected function normalizeReviewMaintenanceQueueItems( array $items ) :array {
				return $this->maintenanceItems;
			}
		};
	}
}
