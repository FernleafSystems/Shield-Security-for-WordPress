<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	FileLocker,
	Malware,
	Vulnerabilities,
	Wordpress
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueAssetFileStatusDetail,
	ActionsQueueBucketsBuilder,
	ActionsQueueGroupMaintenanceSource,
	ActionsQueueGroupScanSource,
	ActionsQueueGroupsBuilder,
	ScansResultsRailTabAvailability
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	MaintenancePluginsService,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Request,
	Users
};

/**
 * @phpstan-import-type GroupSectionData from ActionsQueueGroupsBuilder
 */
class ActionsQueueGroupsBuilderTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
			\define( 'HOUR_IN_SECONDS', 3600 );
		}
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		Functions\when( 'number_format_i18n' )->alias( static fn( int $number ) :string => (string)$number );
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
		ServicesState::mergeItems( [
			'service_wpgeneral' => new class extends General {
				public function ajaxURL() :string {
					return '/admin-ajax.php';
				}
			},
			'service_request' => new class extends Request {
				public function ip() :string {
					return '127.0.0.1';
				}

				public function ts( bool $update = true ) :int {
					return 1700000000;
				}
			},
			'service_wpusers' => new class extends Users {
				public function getCurrentWpUserId() {
					return 0;
				}
			},
			'service_wpplugins' => new MaintenancePluginsService( [
				'updates'       => [],
				'plugins'       => [],
				'active'        => [],
				'plugin_vos'    => [],
				'activate_urls' => [],
				'upgrade_urls'  => [],
			] ),
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_build_group_scan_source_constructs_with_real_scan_asset_cards_builder_wiring() :void {
		$builder = new class extends ActionsQueueGroupsBuilder {
			public function exposeBuildGroupScanSource() :ActionsQueueGroupScanSource {
				return $this->buildGroupScanSource();
			}
		};

		$this->assertInstanceOf( ActionsQueueGroupScanSource::class, $builder->exposeBuildGroupScanSource() );
	}

	public function test_build_expands_scan_bucket_into_per_asset_and_linked_groups() :void {
		$builder = $this->createBuilder(
			[
				$this->makeQueueAssetSummary( 'example-plugin', 'Example Plugin', 3, 'plugin', 'example-plugin/example-plugin.php' ),
			],
			[
				$this->makeQueueAssetSummary( 'example-theme', 'Example Theme', 1, 'theme', 'example-theme' ),
			],
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
						'key'      => 'wp_files',
						'count'    => 4,
						'severity' => 'critical',
						'zone'     => 'scans',
					],
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
					[
						'key'      => 'file_locker',
						'count'    => 5,
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

		$groups = $this->flattenLayerGroups( $data );

		$this->assertSame( 'critical', $data[ 'bucket_selection' ][ 'key' ] );
		$this->assertSame( 'critical', $data[ 'bucket_selection' ][ 'status' ] );
		$this->assertSame( 17, $data[ 'bucket_selection' ][ 'item_count' ] );
		$this->assertSame(
			[
				'wordpress',
				'malware',
				'file_locker',
				'vulnerabilities:vulnerability-example-plugin',
				'plugins:example-plugin',
				'themes:example-theme',
				'abandoned:abandoned-example-theme',
			],
			\array_column( $groups, 'key' )
		);
		$this->assertSame(
			[ 'expandable', 'expandable', 'expandable', 'linked', 'expandable', 'expandable', 'linked' ],
			\array_column( $groups, 'card_type' )
		);
		$this->assertSame(
			[
				[ 'wordpress', 'malware', 'file_locker' ],
				[ 'vulnerabilities:vulnerability-example-plugin' ],
				[ 'plugins:example-plugin' ],
				[ 'themes:example-theme' ],
				[ 'abandoned:abandoned-example-theme' ],
			],
			$this->sectionGroupKeys( $data[ 'active_sections' ] )
		);
		$this->assertSame(
			[ 'wordpress', 'malware', 'file_locker' ],
			\array_column( $data[ 'active_sections' ][ 0 ][ 'groups' ], 'key' )
		);
		$this->assertSame( Wordpress::class, $groups[ 0 ][ 'render_action_class' ] );
		$this->assertSame( Malware::class, $groups[ 1 ][ 'render_action_class' ] );
		$this->assertSame( FileLocker::class, $groups[ 2 ][ 'render_action_class' ] );
		$this->assertSame( Vulnerabilities::class, $groups[ 3 ][ 'render_action_class' ] );
		$this->assertSame( [ 'section' => 'vulnerable' ], $groups[ 3 ][ 'render_action_data' ] );
		$this->assertSame(
			[
				'display_context'         => 'actions_queue',
				'results_display_options' => [
					'include_ignored'  => false,
					'include_repaired' => false,
					'include_deleted'  => false,
					'ignored_only'     => false,
				],
			],
			$groups[ 1 ][ 'render_action_data' ]
		);
		$this->assertSame(
			[
				[
					'href'       => '/wp-admin/update-core.php',
					'target'     => '',
					'rel'        => '',
					'icon_class' => '',
				],
				[
					'href'       => 'https://lookup.example/plugin',
					'target'     => '_blank',
					'rel'        => 'noopener noreferrer',
					'icon_class' => 'bi-box-arrow-up-right',
				],
			],
			\array_map(
				static fn( array $link ) :array => [
					'href'       => $link[ 'href' ] ?? '',
					'target'     => $link[ 'target' ] ?? '',
					'rel'        => $link[ 'rel' ] ?? '',
					'icon_class' => $link[ 'icon_class' ] ?? '',
				],
				$groups[ 3 ][ 'links' ]
			)
		);
		$this->assertSame( 'direct_table', $groups[ 4 ][ 'detail_shell' ] );
		$this->assertSame( [], $groups[ 4 ][ 'detail_table' ] );
		$this->assertSame( ActionsQueueAssetFileStatusDetail::class, $groups[ 4 ][ 'render_action_class' ] );
		$this->assertSame(
			[
				'display_context'         => 'actions_queue',
				'results_display_options' => [
					'include_ignored'  => false,
					'include_repaired' => false,
					'include_deleted'  => false,
					'ignored_only'     => false,
				],
				'subject_type'            => 'plugin',
				'subject_id'              => 'example-plugin/example-plugin.php',
			],
			$groups[ 4 ][ 'render_action_data' ]
		);
		$this->assertSame( 'ajax_render', $groups[ 4 ][ 'selection' ][ 'detail_render_action' ][ 'ex' ] ?? '' );
		$this->assertSame( 'actions_queue_asset_file_status_detail', $groups[ 4 ][ 'selection' ][ 'detail_render_action' ][ 'render_slug' ] ?? '' );
		$this->assertSame( 'plugin', $groups[ 4 ][ 'selection' ][ 'detail_render_action' ][ 'subject_type' ] ?? '' );
		$this->assertSame(
			'example-plugin/example-plugin.php',
			$groups[ 4 ][ 'selection' ][ 'detail_render_action' ][ 'subject_id' ] ?? ''
		);
		$this->assertSame( 'actions_queue', $groups[ 4 ][ 'selection' ][ 'detail_render_action' ][ 'display_context' ] ?? '' );
		$this->assertNotSame( '', $groups[ 4 ][ 'drill_hint' ] );
		$this->assertNotSame( '', $groups[ 1 ][ 'narrative' ] );
		$this->assertNotSame( '', $groups[ 1 ][ 'drill_hint' ] );
		$this->assertSame( 'scanresults_malware', $groups[ 1 ][ 'selection' ][ 'detail_render_action' ][ 'render_slug' ] ?? '' );
	}

	public function test_build_keeps_file_integrity_heading_for_wordpress_only_active_findings() :void {
		$data = $this->createBuilder()->build(
			'critical',
			[
				'items' => [
					[
						'key'      => 'wp_files',
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

		$this->assertSame( [ [ 'wordpress' ] ], $this->sectionGroupKeys( $data[ 'active_sections' ] ) );
		$this->assertSame( [ 'wordpress' ], \array_column( $data[ 'active_sections' ][ 0 ][ 'groups' ], 'key' ) );
		$this->assertSame( [], $data[ 'healthy_sections' ] );
	}

	public function test_build_group_returns_selected_plugin_asset_with_lazy_direct_table_detail() :void {
		$builder = $this->createBuilder(
			[
				$this->makeQueueAssetSummary( 'example-plugin', 'Example Plugin', 3, 'plugin', 'example-plugin/example-plugin.php' ),
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
		$this->assertSame( 'direct_table', $group[ 'detail_shell' ] );
		$this->assertSame( 'expandable', $group[ 'card_type' ] );
		$this->assertNotSame( '', $group[ 'drill_hint' ] );
		$this->assertSame( [], $group[ 'detail_table' ] );
		$this->assertSame( ActionsQueueAssetFileStatusDetail::class, $group[ 'render_action_class' ] );
		$this->assertSame( 'example-plugin/example-plugin.php', $group[ 'render_action_data' ][ 'subject_id' ] );
		$this->assertSame( 'plugins:example-plugin', $group[ 'selection' ][ 'key' ] );
		$this->assertSame( 'direct_table', $group[ 'selection' ][ 'detail_shell' ] );
		$this->assertSame( 'actions_queue_asset_file_status_detail', $group[ 'selection' ][ 'detail_render_action' ][ 'render_slug' ] ?? '' );
		$this->assertCount( 1, $group[ 'selection' ][ 'header' ][ 'actions' ] ?? [] );
		$this->assertSame( 'ignore_all', $this->decodeAjaxAction(
			$group[ 'selection' ][ 'header' ][ 'actions' ][ 0 ][ 'ajax_action_json' ] ?? ''
		)[ 'sub_action' ] ?? '' );
	}

	public function test_build_surfaces_disabled_fix_now_scan_groups_as_neutral_clickable_cards() :void {
		$builder = $this->createBuilder(
			[],
			[],
			[],
			[],
			[],
			[],
			0,
			[
				'wordpress' => [
					'is_available'     => false,
					'disabled_reason'  => 'not_enabled',
					'disabled_message' => 'WordPress core scanning is not enabled.',
				],
				'vulnerabilities' => [
					'is_available'     => false,
					'disabled_reason'  => 'upgrade_required',
					'disabled_message' => 'Vulnerability scanning requires an upgrade.',
				],
				'file_locker' => [
					'is_available'     => false,
					'disabled_reason'  => 'upgrade_required',
					'disabled_message' => 'File Locker requires an upgrade.',
				],
			]
		);

		$data = $builder->build(
			'critical',
			[ 'items' => [] ],
			[
				'scans'       => [],
				'maintenance' => [],
			]
		);
		$groups = [];
		foreach ( $this->flattenSections( $data[ 'healthy_sections' ] ) as $group ) {
			$groups[ $group[ 'key' ] ] = $group;
		}

		$this->assertSame( [], $this->flattenSections( $data[ 'active_sections' ] ) );
		$this->assertSame( [ 'wordpress', 'file_locker', 'vulnerabilities' ], \array_keys( $groups ) );
		$this->assertSame( 'neutral', $groups[ 'wordpress' ][ 'status' ] );
		$this->assertTrue( $groups[ 'wordpress' ][ 'is_interactive' ] );
		$this->assertSame( 'expandable', $groups[ 'vulnerabilities' ][ 'card_type' ] );
		$this->assertSame(
			$groups[ 'vulnerabilities' ][ 'status_label' ],
			$groups[ 'vulnerabilities' ][ 'selection' ][ 'header' ][ 'badge' ] ?? ''
		);
		$this->assertSame( 'expandable', $groups[ 'file_locker' ][ 'card_type' ] );
	}

	public function test_build_orders_healthy_good_sections_before_neutral_only_sections_and_keeps_file_integrity_grouped() :void {
		$builder = $this->createBuilder(
			[],
			[],
			[],
			[],
			[],
			[],
			0,
			[
				'plugins' => [
					'is_available'     => false,
					'disabled_reason'  => 'upgrade_required',
					'disabled_message' => 'Plugin file scanning requires an upgrade.',
				],
				'themes' => [
					'is_available'     => false,
					'disabled_reason'  => 'upgrade_required',
					'disabled_message' => 'Theme file scanning requires an upgrade.',
				],
				'malware' => [
					'is_available'     => false,
					'disabled_reason'  => 'upgrade_required',
					'disabled_message' => 'Malware scanning requires an upgrade.',
				],
				'file_locker' => [
					'is_available'     => false,
					'disabled_reason'  => 'upgrade_required',
					'disabled_message' => 'File Locker requires an upgrade.',
				],
			]
		);

		$data = $builder->build(
			'critical',
			[ 'items' => [] ],
			[
				'scans'       => [
					[
						'key'               => 'wp_files',
						'label'             => 'WordPress Files',
						'description'       => 'WordPress core files are healthy.',
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

		$this->assertSame( [], $data[ 'active_sections' ] );
		$this->assertSame(
			[
				[ 'wordpress', 'malware', 'file_locker' ],
				[ 'abandoned' ],
				[ 'plugins' ],
				[ 'themes' ],
			],
			\array_map(
				static fn( array $section ) :array => \array_column( $section[ 'groups' ], 'key' ),
				$data[ 'healthy_sections' ]
			)
		);
		$this->assertSame(
			[ 'good', 'neutral', 'neutral' ],
			\array_column( $data[ 'healthy_sections' ][ 0 ][ 'groups' ], 'status' )
		);
	}

	public function test_build_with_selected_disabled_scan_group_uses_neutral_header_and_suppresses_result_controls() :void {
		$builder = $this->createBuilder(
			[],
			[],
			[],
			[],
			[],
			[],
			0,
			[
				'vulnerabilities' => [
					'is_available'     => false,
					'disabled_reason'  => 'upgrade_required',
					'disabled_message' => 'Vulnerability scanning requires an upgrade.',
				],
			]
		);

		$payload = $builder->buildWithSelectedGroup(
			'critical',
			'vulnerabilities',
			[ 'items' => [] ],
			[
				'scans'       => [],
				'maintenance' => [],
			]
		);

		$this->assertSame( 'vulnerabilities', $payload[ 'selected_group' ][ 'key' ] );
		$this->assertSame( 'neutral', $payload[ 'selected_group' ][ 'status' ] );
		$this->assertTrue( $payload[ 'selected_group' ][ 'is_interactive' ] );
		$this->assertSame(
			$payload[ 'selected_group' ][ 'status_label' ],
			$payload[ 'selected_group' ][ 'selection' ][ 'header' ][ 'badge' ] ?? ''
		);
		$this->assertSame( [], $payload[ 'selected_group' ][ 'selection' ][ 'header' ][ 'actions' ] ?? null );
		$this->assertArrayNotHasKey( 'display_options', (array)( $payload[ 'selected_group' ][ 'selection' ][ 'header' ] ?? [] ) );
	}

	public function test_build_review_bucket_groups_requested_system_and_wordpress_items_without_changing_plugin_maintenance_cards() :void {
		$builder = $this->createBuilder(
			[],
			[],
			[],
			[
				[
					'key'           => 'system_ssl_certificate',
					'zone'          => 'maintenance',
					'label'         => 'SSL Certificate',
					'icon_class'    => 'bi bi-shield-lock-fill',
					'count'         => 1,
					'severity'      => 'warning',
					'drill_bucket'  => 'review',
					'description'   => 'The SSL certificate should be reviewed.',
					'href'          => '/wp-admin/site-health.php',
					'action'        => 'Open Site Health',
					'target'        => '',
					'cta'           => [
						'href'  => '/wp-admin/site-health.php',
						'label' => 'Open Site Health',
					],
					'toggle_action' => [],
					'expansion'     => [],
				],
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
					'action'        => 'Open Site Health',
					'target'        => '',
					'cta'           => [],
					'toggle_action' => [
						'kind'             => 'unignore',
						'label'            => 'Stop ignoring',
						'href'             => 'javascript:{}',
						'icon'             => 'bi bi-eye-fill',
						'tooltip'          => 'Stop ignoring this maintenance item',
						'target'           => '',
						'ajax_action_json' => '{"ex":"maintenance_item_unignore"}',
					],
					'expansion'     => [],
				],
				[
					'key'           => 'wp_updates',
					'zone'          => 'maintenance',
					'label'         => 'WordPress Version',
					'icon_class'    => 'bi bi-wordpress',
					'count'         => 1,
					'severity'      => 'warning',
					'drill_bucket'  => 'review',
					'description'   => 'A WordPress update is available.',
					'href'          => '/wp-admin/update-core.php',
					'action'        => 'Manage Updates',
					'target'        => '',
					'cta'           => [
						'href'  => '/wp-admin/update-core.php',
						'label' => 'Manage Updates',
					],
					'toggle_action' => [],
					'expansion'     => [],
				],
				[
					'key'           => 'wp_db_password',
					'zone'          => 'maintenance',
					'label'         => 'MySQL DB Password',
					'icon_class'    => 'bi bi-database-fill-lock',
					'count'         => 0,
					'severity'      => 'good',
					'drill_bucket'  => 'review',
					'description'   => 'The database password is strong.',
					'href'          => '',
					'action'        => '',
					'target'        => '',
					'cta'           => [],
					'toggle_action' => [],
					'expansion'     => [],
				],
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
											'kind'             => 'ignore',
											'label'            => 'Ignore',
											'href'             => 'javascript:{}',
											'icon'             => 'bi bi-eye-slash-fill',
											'tooltip'          => 'Ignore this maintenance item',
											'target'           => '',
											'ajax_action_json' => '{"ex":"maintenance_item_ignore"}',
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
						'key'      => 'system_ssl_certificate',
						'count'    => 1,
						'severity' => 'warning',
						'zone'     => 'maintenance',
					],
					[
						'key'      => 'wp_updates',
						'count'    => 1,
						'severity' => 'warning',
						'zone'     => 'maintenance',
					],
					[
						'key'      => 'wp_plugins_updates',
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
						'key'               => 'system_php_version',
						'label'             => 'PHP Version',
						'description'       => 'This maintenance item is currently ignored.',
						'drill_bucket'      => 'review',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-check-circle-fill',
					],
					[
						'key'               => 'wp_db_password',
						'label'             => 'MySQL DB Password',
						'description'       => 'The database password is strong.',
						'drill_bucket'      => 'review',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-check-circle-fill',
					],
				],
			]
		);

		$activeGroups = [];
		foreach ( $this->flattenSections( $data[ 'active_sections' ] ) as $group ) {
			$activeGroups[ $group[ 'key' ] ] = $group;
		}
		$healthyGroupKeys = \array_column( $this->flattenSections( $data[ 'healthy_sections' ] ), 'key' );

		$activeGroupKeys = \array_keys( $activeGroups );
		\sort( $activeGroupKeys );
		$this->assertSame(
			[ 'maintenance_system', 'maintenance_wordpress', 'wp_plugins_updates' ],
			$activeGroupKeys
		);
		$this->assertNotContains( 'maintenance', \array_keys( $activeGroups ) );
		$this->assertNotContains( 'maintenance_system', $healthyGroupKeys );
		$this->assertNotContains( 'maintenance_wordpress', $healthyGroupKeys );

		$systemGroup = $activeGroups[ 'maintenance_system' ];
		$this->assertArrayHasKey( 'icon_class', $systemGroup );
		$this->assertSame( [], $systemGroup[ 'management_link' ] );
		$this->assertSame( 1, $systemGroup[ 'item_count' ] );
		$systemRows = \array_values( $systemGroup[ 'maintenance_rows' ] );
		$this->assertCount( 2, $systemRows );
		$this->assertSame( '', $systemRows[ 0 ][ 'actions' ][ 0 ][ 'ajax_action_json' ] );
		$this->assertFalse( $systemRows[ 0 ][ 'is_ignored' ] );
		$this->assertTrue( $systemRows[ 1 ][ 'is_ignored' ] );
		$this->assertSame(
			'maintenance_item_unignore',
			$this->decodeAjaxAction( $systemRows[ 1 ][ 'actions' ][ 0 ][ 'ajax_action_json' ] )[ 'ex' ] ?? ''
		);

		$wordpressGroup = $activeGroups[ 'maintenance_wordpress' ];
		$this->assertSame( [], $wordpressGroup[ 'management_link' ] );
		$this->assertSame( 1, $wordpressGroup[ 'item_count' ] );
		$wordpressRows = \array_values( $wordpressGroup[ 'maintenance_rows' ] );
		$this->assertCount( 2, $wordpressRows );
		$this->assertSame( '/wp-admin/update-core.php', $wordpressRows[ 0 ][ 'actions' ][ 0 ][ 'href' ] ?? '' );
		$this->assertSame( [], $wordpressRows[ 1 ][ 'actions' ] ?? [] );

		$pluginUpdatesGroup = $activeGroups[ 'wp_plugins_updates' ];
		$this->assertSame(
			[
				'href'       => '/wp-admin/update-core.php',
				'target'     => '',
				'rel'        => '',
				'icon_class' => 'bi-arrow-right',
			],
			[
				'href'       => $pluginUpdatesGroup[ 'management_link' ][ 'href' ] ?? '',
				'target'     => $pluginUpdatesGroup[ 'management_link' ][ 'target' ] ?? '',
				'rel'        => $pluginUpdatesGroup[ 'management_link' ][ 'rel' ] ?? '',
				'icon_class' => $pluginUpdatesGroup[ 'management_link' ][ 'icon_class' ] ?? '',
			]
		);
		$this->assertNotEmpty( $pluginUpdatesGroup[ 'maintenance_rows' ] );
		$this->assertSame(
			'maintenance_item_ignore',
			$this->decodeAjaxAction( $pluginUpdatesGroup[ 'maintenance_rows' ][ 0 ][ 'actions' ][ 0 ][ 'ajax_action_json' ] )[ 'ex' ] ?? ''
		);
		$this->assertSame( '', $pluginUpdatesGroup[ 'drill_hint' ] );
		$this->assertSame( 'maintenance', $pluginUpdatesGroup[ 'detail_shell' ] );
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
			[ 'vulnerabilities:vulnerability-example-plugin', 'abandoned:abandoned-example-theme' ],
			\array_column( $this->flattenLayerGroups( $data ), 'key' )
		);
		$this->assertSame( 1, $builder->getVulnerabilitiesPayloadCalls() );
	}

	public function test_build_review_bucket_keeps_grouped_healthy_maintenance_groups_resolvable() :void {
		$builder = $this->createBuilder(
			[],
			[],
			[],
			[
				[
					'key'           => 'wp_updates',
					'zone'          => 'maintenance',
					'label'         => 'WordPress Version',
					'icon_class'    => 'bi bi-wordpress',
					'count'         => 0,
					'severity'      => 'good',
					'drill_bucket'  => 'review',
					'description'   => 'WordPress is up to date.',
					'href'          => '/wp-admin/update-core.php',
					'action'        => 'Manage Updates',
					'target'        => '',
					'cta'           => [
						'href'  => '/wp-admin/update-core.php',
						'label' => 'Manage Updates',
					],
					'toggle_action' => [],
					'expansion'     => [],
				],
				[
					'key'           => 'default_admin_user',
					'zone'          => 'maintenance',
					'label'         => 'Default Admin User',
					'icon_class'    => 'bi bi-person-fill-lock',
					'count'         => 0,
					'severity'      => 'good',
					'drill_bucket'  => 'review',
					'description'   => 'This maintenance item is currently ignored.',
					'href'          => '/wp-admin/users.php',
					'action'        => 'Manage Users',
					'target'        => '',
					'cta'           => [
						'href'  => '/wp-admin/users.php',
						'label' => 'Manage Users',
					],
					'toggle_action' => [
						'kind'             => 'unignore',
						'label'            => 'Stop ignoring',
						'href'             => 'javascript:{}',
						'icon'             => 'bi bi-eye-fill',
						'tooltip'          => 'Stop ignoring this maintenance item',
						'target'           => '',
						'ajax_action_json' => '{"ex":"maintenance_item_unignore"}',
					],
					'expansion'     => [],
				],
			]
		);

		$payload = $builder->buildWithSelectedGroup(
			'review',
			'maintenance_wordpress',
			[
				'items' => [
				],
			],
			[
				'scans'       => [],
				'maintenance' => [
					[
						'key'               => 'wp_updates',
						'label'             => 'WordPress Version',
						'description'       => 'WordPress is up to date.',
						'drill_bucket'      => 'review',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-check-circle-fill',
					],
					[
						'key'               => 'default_admin_user',
						'label'             => 'Default Admin User',
						'description'       => 'This maintenance item is currently ignored.',
						'drill_bucket'      => 'review',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-patch-check-fill',
					],
				],
			]
		);

		$this->assertSame( [], $this->flattenSections( $payload[ 'layer' ][ 'active_sections' ] ) );
		$this->assertSame( [ 'maintenance_wordpress' ], \array_column( $this->flattenSections( $payload[ 'layer' ][ 'healthy_sections' ] ), 'key' ) );
		$this->assertSame( 'good', $this->flattenSections( $payload[ 'layer' ][ 'healthy_sections' ] )[ 0 ][ 'status' ] );
		$this->assertSame( '', $this->flattenSections( $payload[ 'layer' ][ 'healthy_sections' ] )[ 0 ][ 'drill_hint' ] );
		$this->assertArrayNotHasKey( 'next_move', $payload[ 'selected_group' ] );
		$this->assertSame( 'maintenance_wordpress', $payload[ 'selected_group' ][ 'key' ] );
		$this->assertSame( 2, $payload[ 'selected_group' ][ 'item_count' ] );
		$this->assertSame( [], $payload[ 'selected_group' ][ 'management_link' ] );
		$selectedRows = \array_values( $payload[ 'selected_group' ][ 'maintenance_rows' ] );
		$this->assertCount( 2, $selectedRows );
		$this->assertSame( '/wp-admin/update-core.php', $selectedRows[ 0 ][ 'actions' ][ 0 ][ 'href' ] ?? '' );
		$this->assertSame( '/wp-admin/users.php', $selectedRows[ 1 ][ 'actions' ][ 0 ][ 'href' ] ?? '' );
		$this->assertSame(
			'maintenance_item_unignore',
			$this->decodeAjaxAction( $selectedRows[ 1 ][ 'actions' ][ 1 ][ 'ajax_action_json' ] )[ 'ex' ] ?? ''
		);
	}

	public function test_build_critical_bucket_keeps_healthy_vulnerability_and_abandoned_groups_separate() :void {
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

		$this->assertSame( [ [ 'vulnerabilities' ], [ 'abandoned' ] ], $this->sectionGroupKeys( $payload[ 'layer' ][ 'healthy_sections' ] ) );
		$healthyGroups = $this->flattenSections( $payload[ 'layer' ][ 'healthy_sections' ] );
		$this->assertSame( [ 'vulnerabilities', 'abandoned' ], \array_column( $healthyGroups, 'key' ) );
		$this->assertSame( 'linked', $healthyGroups[ 0 ][ 'card_type' ] );
		$this->assertSame( 'linked', $healthyGroups[ 1 ][ 'card_type' ] );
		$this->assertSame( [], $healthyGroups[ 0 ][ 'links' ] );
		$this->assertSame( [], $healthyGroups[ 1 ][ 'links' ] );
		$this->assertFalse( $healthyGroups[ 0 ][ 'is_interactive' ] );
		$this->assertFalse( $healthyGroups[ 1 ][ 'is_interactive' ] );
		$this->assertSame( [], $healthyGroups[ 0 ][ 'render_action_data' ] );
		$this->assertSame( [], $healthyGroups[ 1 ][ 'render_action_data' ] );
		$this->assertSame( 'vulnerabilities', $payload[ 'selected_group' ][ 'key' ] );
		$this->assertSame( 'linked', $payload[ 'selected_group' ][ 'card_type' ] );
		$this->assertFalse( $payload[ 'selected_group' ][ 'is_interactive' ] );
		$this->assertSame( [], $payload[ 'selected_group' ][ 'render_action_data' ] );
		$this->assertSame( '', $payload[ 'selected_group' ][ 'drill_hint' ] );
	}

	public function test_build_with_selected_group_resolves_healthy_abandoned_group_without_falling_back_to_vulnerabilities() :void {
		$builder = $this->createBuilder();

		$payload = $builder->buildWithSelectedGroup(
			'critical',
			'abandoned',
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

		$this->assertSame( 'abandoned', $payload[ 'selected_group' ][ 'key' ] );
		$this->assertSame( [], $payload[ 'selected_group' ][ 'render_action_data' ] );
		$this->assertFalse( $payload[ 'selected_group' ][ 'is_interactive' ] );
	}

	public function test_build_keeps_healthy_scan_sections_visible_after_active_vulnerability_section() :void {
		$builder = $this->createBuilder(
			[],
			[],
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
								'actions'     => [],
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
						'key'      => 'vulnerable_assets',
						'count'    => 1,
						'severity' => 'critical',
						'zone'     => 'scans',
					],
				],
			],
			[
				'scans'       => [
					[
						'key'               => 'theme_files',
						'label'             => 'Theme Files',
						'description'       => 'All theme files appear to be valid.',
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

		$this->assertSame( [ [ 'vulnerabilities:vulnerability-example-plugin' ] ], $this->sectionGroupKeys( $data[ 'active_sections' ] ) );
		$this->assertSame( [ [ 'themes' ], [ 'abandoned' ] ], $this->sectionGroupKeys( $data[ 'healthy_sections' ] ) );
		$this->assertSame(
			[ 'vulnerabilities:vulnerability-example-plugin' ],
			\array_column( $data[ 'active_sections' ][ 0 ][ 'groups' ], 'key' )
		);
		$this->assertSame(
			[ 'themes', 'abandoned' ],
			\array_column( $this->flattenSections( $data[ 'healthy_sections' ] ), 'key' )
		);
	}

	public function test_build_with_selected_group_keeps_healthy_file_locker_interactive_at_zero_items() :void {
		$builder = $this->createBuilder();

		$payload = $builder->buildWithSelectedGroup(
			'critical',
			'file_locker',
			[
				'items' => [],
			],
			[
				'scans'       => [
					[
						'key'               => 'file_locker',
						'label'             => 'File Locker',
						'description'       => 'Locked files are healthy.',
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

		$this->assertSame( [ 'file_locker' ], \array_column( $healthyGroups, 'key' ) );
		$this->assertSame( 'asset_cards', $healthyGroups[ 0 ][ 'detail_shell' ] );
		$this->assertTrue( $healthyGroups[ 0 ][ 'is_interactive' ] );
		$this->assertSame( 0, $healthyGroups[ 0 ][ 'item_count' ] );
		$this->assertSame( 'actions_queue', $healthyGroups[ 0 ][ 'render_action_data' ][ 'display_context' ] ?? '' );
		$this->assertSame( 'file_locker', $payload[ 'selected_group' ][ 'key' ] );
		$this->assertSame( 'asset_cards', $payload[ 'selected_group' ][ 'detail_shell' ] );
		$this->assertTrue( $payload[ 'selected_group' ][ 'is_interactive' ] );
		$this->assertSame( 0, $payload[ 'selected_group' ][ 'item_count' ] );
		$this->assertSame( 'actions_queue', $payload[ 'selected_group' ][ 'render_action_data' ][ 'display_context' ] ?? '' );
		$this->assertSame( 'scanresults_filelocker', $payload[ 'selected_group' ][ 'selection' ][ 'detail_render_action' ][ 'render_slug' ] ?? '' );
	}

	public function test_build_critical_bucket_uses_explicit_asset_groups_for_fully_ignored_assets() :void {
		$builder = $this->createBuilder(
			[],
			[],
			[],
			[],
			[
				\array_merge(
					$this->makeQueueAssetSummary( 'ignored-plugin', 'asset-title-plugin-ignored', 2, 'plugin', 'ignored-plugin/ignored-plugin.php' ),
					[
						'stat_text' => 'ignored',
					]
				),
			],
			[
				\array_merge(
					$this->makeQueueAssetSummary( 'ignored-theme', 'asset-title-ignored', 4, 'theme', 'ignored-theme' ),
					[
						'stat_text' => 'ignored',
					]
				),
			],
			3
		);

		$data = $builder->build(
			'critical',
			[
				'items' => [
					[
						'key'      => 'plugin_files_ignored',
						'count'    => 1,
						'severity' => 'warning',
						'zone'     => 'scans',
					],
					[
						'key'      => 'theme_files_ignored',
						'count'    => 1,
						'severity' => 'warning',
						'zone'     => 'scans',
					],
					[
						'key'      => 'malware_ignored',
						'count'    => 5,
						'severity' => 'warning',
						'zone'     => 'scans',
					],
				],
			],
			[
				'scans'       => [
					[
						'key'               => 'wp_files',
						'label'             => 'scan-label-wp',
						'description'       => 'scan-description-wp',
						'drill_bucket'      => 'critical',
						'status'            => 'good',
						'status_label'      => 'status-label-good',
						'status_icon_class' => 'bi bi-patch-check-fill',
					],
					[
						'key'               => 'plugin_files',
						'label'             => 'scan-label-plugin',
						'description'       => 'scan-description-plugin',
						'drill_bucket'      => 'critical',
						'status'            => 'good',
						'status_label'      => 'status-label-good',
						'status_icon_class' => 'bi bi-patch-check-fill',
					],
					[
						'key'               => 'theme_files',
						'label'             => 'scan-label-theme',
						'description'       => 'scan-description-theme',
						'drill_bucket'      => 'critical',
						'status'            => 'good',
						'status_label'      => 'status-label-good',
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
		$activeGroups = [];
		foreach ( $this->flattenSections( $data[ 'active_sections' ] ) as $group ) {
			$activeGroups[ $group[ 'key' ] ] = $group;
		}

		$this->assertTrue( $groups[ 'wordpress' ][ 'is_interactive' ] );
		$this->assertSame(
			[
				'display_context'         => 'actions_queue',
				'results_display_options' => [
					'include_ignored'  => false,
					'include_repaired' => false,
					'include_deleted'  => false,
					'ignored_only'     => false,
				],
			],
			$groups[ 'wordpress' ][ 'render_action_data' ]
		);
		$this->assertSame( 3, $groups[ 'wordpress' ][ 'item_count' ] );
		$this->assertSame( [], $groups[ 'wordpress' ][ 'selection' ][ 'header' ][ 'actions' ] ?? null );
		$this->assertSame( 'scanresults_wordpress', $groups[ 'wordpress' ][ 'selection' ][ 'detail_render_action' ][ 'render_slug' ] ?? '' );
		$this->assertSame( 'actions_queue', $groups[ 'wordpress' ][ 'selection' ][ 'detail_render_action' ][ 'display_context' ] ?? '' );
		$this->assertSame(
			[
				'include_ignored'  => false,
				'include_repaired' => false,
				'include_deleted'  => false,
				'ignored_only'     => false,
			],
			$groups[ 'wordpress' ][ 'selection' ][ 'detail_render_action' ][ 'results_display_options' ] ?? []
		);
		$this->assertArrayNotHasKey( 'plugins', $groups );
		$this->assertArrayNotHasKey( 'themes', $groups );
		$this->assertArrayHasKey( 'plugins:ignored-plugin', $activeGroups );
		$this->assertSame( 'warning', $activeGroups[ 'plugins:ignored-plugin' ][ 'status' ] );
		$this->assertSame( 2, $activeGroups[ 'plugins:ignored-plugin' ][ 'item_count' ] );
		$this->assertNotSame( '', $activeGroups[ 'plugins:ignored-plugin' ][ 'narrative' ] );
		$this->assertSame( [], $activeGroups[ 'plugins:ignored-plugin' ][ 'selection' ][ 'header' ][ 'actions' ] ?? null );
		$this->assertSame(
			[
				'include_ignored'  => false,
				'include_repaired' => false,
				'include_deleted'  => false,
				'ignored_only'     => false,
			],
			$activeGroups[ 'plugins:ignored-plugin' ][ 'render_action_data' ][ 'results_display_options' ] ?? []
		);
		$this->assertArrayHasKey( 'themes:ignored-theme', $activeGroups );
		$this->assertSame( 'warning', $activeGroups[ 'themes:ignored-theme' ][ 'status' ] );
		$this->assertSame( 4, $activeGroups[ 'themes:ignored-theme' ][ 'item_count' ] );
		$this->assertSame(
			[
				'include_ignored'  => false,
				'include_repaired' => false,
				'include_deleted'  => false,
				'ignored_only'     => false,
			],
			$activeGroups[ 'themes:ignored-theme' ][ 'render_action_data' ][ 'results_display_options' ] ?? []
		);
		$this->assertArrayHasKey( 'malware', $activeGroups );
		$this->assertTrue( $activeGroups[ 'malware' ][ 'is_interactive' ] );
		$this->assertSame( 'direct_table', $activeGroups[ 'malware' ][ 'detail_shell' ] );
		$this->assertSame( 'scanresults_malware', $activeGroups[ 'malware' ][ 'selection' ][ 'detail_render_action' ][ 'render_slug' ] ?? '' );
		$this->assertSame(
			[
				'include_ignored'  => false,
				'include_repaired' => false,
				'include_deleted'  => false,
				'ignored_only'     => false,
			],
			$activeGroups[ 'malware' ][ 'render_action_data' ][ 'results_display_options' ] ?? []
		);
		$this->assertSame( [], $activeGroups[ 'malware' ][ 'selection' ][ 'header' ][ 'actions' ] ?? null );
		$this->assertSame( [ [ 'wordpress' ] ], $this->sectionGroupKeys( $data[ 'healthy_sections' ] ) );
	}

	public function test_build_reuses_shared_active_and_ignored_pane_options_for_scan_groups() :void {
		$builder = $this->createBuilder(
			[
				$this->makeQueueAssetSummary( 'example-plugin', 'Example Plugin', 3, 'plugin', 'example-plugin/example-plugin.php' ),
			],
			[
				$this->makeQueueAssetSummary( 'example-theme', 'Example Theme', 1, 'theme', 'example-theme' ),
			],
			[],
			[],
			[
				$this->makeQueueAssetSummary( 'ignored-plugin', 'asset-title-plugin-ignored', 2, 'plugin', 'ignored-plugin/ignored-plugin.php' ),
			],
			[
				$this->makeQueueAssetSummary( 'ignored-theme', 'asset-title-ignored', 4, 'theme', 'ignored-theme' ),
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
						'key'      => 'plugin_files_ignored',
						'count'    => 1,
						'severity' => 'warning',
						'zone'     => 'scans',
					],
					[
						'key'      => 'theme_files',
						'count'    => 1,
						'severity' => 'critical',
						'zone'     => 'scans',
					],
					[
						'key'      => 'theme_files_ignored',
						'count'    => 1,
						'severity' => 'warning',
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
				[ 'include_ignored' => false, 'include_repaired' => false, 'include_deleted' => false, 'ignored_only' => false ],
				[ 'include_ignored' => true, 'include_repaired' => false, 'include_deleted' => false, 'ignored_only' => true ],
			],
			$builder->getPluginPaneCalls()
		);
		$this->assertSame(
			[
				[ 'include_ignored' => false, 'include_repaired' => false, 'include_deleted' => false, 'ignored_only' => false ],
				[ 'include_ignored' => true, 'include_repaired' => false, 'include_deleted' => false, 'ignored_only' => true ],
			],
			$builder->getThemePaneCalls()
		);
	}

	/**
	 * @param list<GroupSectionData> $sections
	 * @return list<array<string,mixed>>
	 */
	private function flattenSections( array $sections ) :array {
		return \array_merge( [], ...\array_map(
			static fn( array $section ) :array => $section[ 'groups' ],
			$sections
		) );
	}

	/**
	 * @param list<GroupSectionData> $sections
	 * @return list<list<string>>
	 */
	private function sectionGroupKeys( array $sections ) :array {
		return \array_map(
			static fn( array $section ) :array => \array_column( $section[ 'groups' ], 'key' ),
			$sections
		);
	}

	/**
	 * @param array{active_sections:list<GroupSectionData>,healthy_sections:list<GroupSectionData>} $layer
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
	private function decodeAjaxAction( string $ajaxActionJson ) :array {
		$decoded = \json_decode( $ajaxActionJson, true );
		return \is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function makeQueueAssetSummary( string $key, string $title, int $count, string $subjectType, string $subjectId ) :array {
		return [
			'key'          => $key,
			'status'       => 'warning',
			'icon_class'   => $subjectType === 'plugin' ? 'bi bi-plug-fill' : 'bi bi-palette-fill',
			'title'        => $title,
			'stat_text'    => \sprintf( $count === 1 ? '%s file needs review' : '%s files need review', $count ),
			'meta_text'    => $subjectId,
			'count_badge'  => $count,
			'subject_type' => $subjectType,
			'subject_id'   => $subjectId,
			'has_update'   => false,
		];
	}

	private function createBuilder(
		array $pluginCards = [],
		array $themeCards = [],
		array $vulnerabilities = [],
		array $maintenanceItems = [],
		array $ignoredPluginCards = [],
		array $ignoredThemeCards = [],
		int $ignoredWordpressCount = 0,
		array $tabAvailability = []
	) :ActionsQueueGroupsBuilder {
		return new class(
			$pluginCards,
			$themeCards,
			$vulnerabilities,
			$maintenanceItems,
			$ignoredPluginCards,
			$ignoredThemeCards,
			$ignoredWordpressCount,
			$tabAvailability
		) extends ActionsQueueGroupsBuilder {

			private ?ActionsQueueGroupScanSource $scanSource = null;
			private ?ActionsQueueGroupMaintenanceSource $maintenanceSource = null;
			private array $pluginCards;
			private array $themeCards;
			private array $vulnerabilities;
			private array $maintenanceItems;
			private array $ignoredPluginCards;
			private array $ignoredThemeCards;
			private int $ignoredWordpressCount;
			private array $tabAvailability;

			public function __construct(
				array $pluginCards,
				array $themeCards,
				array $vulnerabilities,
				array $maintenanceItems,
				array $ignoredPluginCards,
				array $ignoredThemeCards,
				int $ignoredWordpressCount,
				array $tabAvailability
			) {
				$this->pluginCards = $pluginCards;
				$this->themeCards = $themeCards;
				$this->vulnerabilities = $vulnerabilities;
				$this->maintenanceItems = $maintenanceItems;
				$this->ignoredPluginCards = $ignoredPluginCards;
				$this->ignoredThemeCards = $ignoredThemeCards;
				$this->ignoredWordpressCount = $ignoredWordpressCount;
				$this->tabAvailability = $tabAvailability;
			}

			protected function buildBucketsBuilder() :ActionsQueueBucketsBuilder {
				return new ActionsQueueBucketsBuilder( $this->buildRailTabAvailability() );
			}

			protected function buildRailTabAvailability() :ScansResultsRailTabAvailability {
				return new class( $this->tabAvailability ) extends ScansResultsRailTabAvailability {

					private array $states;

					public function __construct( array $states ) {
						$this->states = $states;
					}

					public function build( string $tabKey ) :array {
						$state = $this->states[ $tabKey ] ?? [];
						$isAvailable = (bool)( $state[ 'is_available' ] ?? true );

						return \array_merge( [
							'is_available'          => $isAvailable,
							'show_in_actions_queue' => true,
							'show_in_fix_now'       => true,
							'disabled_reason'       => '',
							'disabled_message'      => '',
							'disabled_status'       => 'neutral',
							'disabled_actions'      => [],
						], $state );
					}
				};
			}

			protected function buildGroupScanSource() :ActionsQueueGroupScanSource {
				if ( $this->scanSource === null ) {
					$this->scanSource = new class(
						$this->pluginCards,
						$this->themeCards,
						$this->vulnerabilities,
						$this->ignoredPluginCards,
						$this->ignoredThemeCards,
						$this->ignoredWordpressCount
					) extends ActionsQueueGroupScanSource {

						private int $vulnerabilitiesPayloadCalls = 0;
						private array $pluginPaneCalls = [];
						private array $themePaneCalls = [];
						private ?array $vulnerabilitiesPayload = null;
						private array $fullyIgnoredSummariesLoaded = [];
						private array $pluginCards;
						private array $themeCards;
						private array $vulnerabilities;
						private array $ignoredPluginCards;
						private array $ignoredThemeCards;
						private int $ignoredWordpressCount;

						public function __construct(
							array $pluginCards,
							array $themeCards,
							array $vulnerabilities,
							array $ignoredPluginCards,
							array $ignoredThemeCards,
							int $ignoredWordpressCount
						) {
							$this->pluginCards = $pluginCards;
							$this->themeCards = $themeCards;
							$this->vulnerabilities = $vulnerabilities;
							$this->ignoredPluginCards = $ignoredPluginCards;
							$this->ignoredThemeCards = $ignoredThemeCards;
							$this->ignoredWordpressCount = $ignoredWordpressCount;
						}

						public function activeAssetSummariesForSource( string $assetSource ) :array {
							if ( $assetSource === 'plugins' ) {
								$this->pluginPaneCalls[] = [
									'include_ignored'  => false,
									'include_repaired' => false,
									'include_deleted'  => false,
									'ignored_only'     => false,
								];
								return $this->pluginCards;
							}

							if ( $assetSource === 'themes' ) {
								$this->themePaneCalls[] = [
									'include_ignored'  => false,
									'include_repaired' => false,
									'include_deleted'  => false,
									'ignored_only'     => false,
								];
								return $this->themeCards;
							}

							return [];
						}

						public function ignoredCountForSource( string $ignoredSource ) :int {
							if ( $ignoredSource === 'wordpress' ) {
								return $this->ignoredWordpressCount;
							}

							if ( $ignoredSource === 'plugins' ) {
								$this->pluginPaneCalls[] = [
									'include_ignored'  => true,
									'include_repaired' => false,
									'include_deleted'  => false,
									'ignored_only'     => true,
								];
								return (int)\array_sum( \array_column( $this->ignoredPluginCards, 'count_badge' ) );
							}

							if ( $ignoredSource === 'themes' ) {
								$this->themePaneCalls[] = [
									'include_ignored'  => true,
									'include_repaired' => false,
									'include_deleted'  => false,
									'ignored_only'     => true,
								];
								return (int)\array_sum( \array_column( $this->ignoredThemeCards, 'count_badge' ) );
							}

							return 0;
						}

						public function fullyIgnoredAssetSummariesForSource( string $assetSource ) :array {
							if ( !isset( $this->fullyIgnoredSummariesLoaded[ $assetSource ] ) ) {
								$this->fullyIgnoredSummariesLoaded[ $assetSource ] = true;
								if ( $assetSource === 'plugins' ) {
									$this->pluginPaneCalls[] = [
										'include_ignored'  => true,
										'include_repaired' => false,
										'include_deleted'  => false,
										'ignored_only'     => true,
									];
								}
								if ( $assetSource === 'themes' ) {
									$this->themePaneCalls[] = [
										'include_ignored'  => true,
										'include_repaired' => false,
										'include_deleted'  => false,
										'ignored_only'     => true,
									];
								}
							}

							if ( $assetSource === 'plugins' ) {
								return $this->ignoredPluginCards;
							}
							if ( $assetSource === 'themes' ) {
								return $this->ignoredThemeCards;
							}

							return [];
						}

						public function vulnerabilitySection( string $sectionKey ) :array {
							if ( $this->vulnerabilitiesPayload === null ) {
								$this->vulnerabilitiesPayloadCalls++;
								$this->vulnerabilitiesPayload = $this->vulnerabilities !== []
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
							return $this->vulnerabilitiesPayload[ 'sections' ][ $sectionKey ];
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
					};
				}

				return $this->scanSource;
			}

			protected function buildGroupMaintenanceSource() :ActionsQueueGroupMaintenanceSource {
				if ( $this->maintenanceSource === null ) {
					$this->maintenanceSource = new class( $this->maintenanceItems ) extends ActionsQueueGroupMaintenanceSource {

						private array $maintenanceItems;

						public function __construct( array $maintenanceItems ) {
							$this->maintenanceItems = $maintenanceItems;
						}

						public function itemsForBucket( array $bucketSource, string $bucketKey ) :array {
							return $this->maintenanceItems;
						}
					};
				}

				return $this->maintenanceSource;
			}

			public function getVulnerabilitiesPayloadCalls() :int {
				return $this->buildGroupScanSource()->getVulnerabilitiesPayloadCalls();
			}

			public function getPluginPaneCalls() :array {
				return $this->buildGroupScanSource()->getPluginPaneCalls();
			}

			public function getThemePaneCalls() :array {
				return $this->buildGroupScanSource()->getThemePaneCalls();
			}
		};
	}
}
