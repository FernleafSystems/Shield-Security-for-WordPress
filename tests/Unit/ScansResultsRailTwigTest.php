<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\DetailExpansionType;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Twig\{
	Environment,
	Loader\FilesystemLoader,
	TemplateWrapper
};

class ScansResultsRailTwigTest extends BaseUnitTest {

	use PluginPathsTrait;

	private function twig() :Environment {
		return new Environment(
			new FilesystemLoader( $this->getPluginFilePath( 'templates/twig' ) ),
			[
				'cache'            => false,
				'debug'            => false,
				'strict_variables' => false,
			]
		);
	}

	private function createDomXPathFromHtml( string $html ) :\DOMXPath {
		$doc = new \DOMDocument();
		$previous = \libxml_use_internal_errors( true );
		try {
			$doc->loadHTML(
				'<?xml encoding="utf-8" ?>'.$html,
				\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
			);
		}
		finally {
			\libxml_clear_errors();
			\libxml_use_internal_errors( $previous );
		}

		return new \DOMXPath( $doc );
	}

	private function assertXPathExists( \DOMXPath $xpath, string $query, string $label ) :\DOMNode {
		$nodes = $xpath->query( $query );
		$this->assertNotFalse( $nodes, $label.' query failed: '.$query );
		$this->assertGreaterThan( 0, $nodes->length, $label.' missing for query: '.$query );

		return $nodes->item( 0 );
	}

	/**
	 * @param list<array<string,mixed>> $tabs
	 * @return list<array<string,mixed>>
	 */
	private function normalizeRailTabs( array $tabs ) :array {
		return \array_map( fn( array $tab ) :array => $this->normalizeRailTab( $tab ), $tabs );
	}

	/**
	 * @param array<string,mixed> $tab
	 * @return array<string,mixed>
	 */
	private function normalizeRailTab( array $tab ) :array {
		$items = \is_array( $tab[ 'items' ] ?? null ) ? \array_values( $tab[ 'items' ] ) : [];

		return \array_merge( [
			'pane_id'                => '',
			'nav_id'                 => '',
			'label'                  => '',
			'count'                  => 0,
			'is_active'              => false,
			'target'                 => '',
			'controls'               => '',
			'icon_class'             => '',
			'status'                 => 'good',
			'items'                  => \array_map( fn( array $item ) :array => $this->normalizeDetailRow( $item ), $items ),
			'is_loaded'              => true,
			'is_disabled'            => false,
			'disabled_message'       => '',
			'disabled_status'        => 'neutral',
			'render_action'          => [],
			'show_count_placeholder' => false,
		], $tab );
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private function normalizeDetailRow( array $row ) :array {
		return \array_merge( [
			'title'           => '',
			'description'     => '',
			'status'          => 'good',
			'status_icon'     => null,
			'status_label'    => null,
			'count_badge'     => null,
			'badge_status'    => null,
			'expandable'      => false,
			'expand_target'   => '',
			'expansion'       => [],
			'explanations'    => [],
			'show_gear'       => false,
			'actions'         => [],
			'attributes'      => [],
			'section_label'   => '',
		], $row );
	}

	private function buildRenderContext() :array {
		$context = [
			'strings' => [
				'pane_loading' => 'Loading scan details...',
				'no_issues'    => 'No issues found in this section.',
			],
			'vars'    => [
				'rail'            => [
					'id'            => 'ScanResultsRailSidebar',
					'accent_status' => 'critical',
					'items'         => [
						[
							'key'       => 'summary',
							'label'     => 'Summary',
							'icon_class' => 'bi bi-clipboard2-pulse-fill',
							'status'    => 'critical',
							'count'     => 3,
							'nav_id'    => 'h-tabs-summary-tab',
							'target'    => '#h-tabs-summary',
							'controls'  => 'h-tabs-summary',
							'is_active' => true,
						],
						[
							'key'       => 'plugins',
							'label'     => 'Plugin Files',
							'icon_class' => 'bi bi-plug-fill',
							'status'    => 'warning',
							'count'     => 2,
							'nav_id'    => 'h-tabs-plugins-tab',
							'target'    => '#h-tabs-plugins',
							'controls'  => 'h-tabs-plugins',
							'is_active' => false,
						],
						[
							'key'       => 'vulnerabilities',
							'label'     => 'Vulnerabilities',
							'icon_class' => 'bi bi-shield-exclamation',
							'status'    => 'critical',
							'count'     => 1,
							'nav_id'    => 'h-tabs-vulnerabilities-tab',
							'target'    => '#h-tabs-vulnerabilities',
							'controls'  => 'h-tabs-vulnerabilities',
							'is_active' => false,
						],
						[
							'key'       => 'malware',
							'label'     => 'Malware',
							'icon_class' => 'bi bi-bug-fill',
							'status'    => 'good',
							'count'     => 0,
							'nav_id'    => 'h-tabs-malware-tab',
							'target'    => '#h-tabs-malware',
							'controls'  => 'h-tabs-malware',
							'is_active' => false,
						],
					],
				],
				'tabs'            => [
					[
						'key'       => 'summary',
						'pane_id'   => 'h-tabs-summary',
						'nav_id'    => 'h-tabs-summary-tab',
						'label'     => 'Summary',
						'count'     => 3,
						'is_active' => true,
						'target'    => '#h-tabs-summary',
						'controls'  => 'h-tabs-summary',
					],
					[
						'key'       => 'plugins',
						'pane_id'   => 'h-tabs-plugins',
						'nav_id'    => 'h-tabs-plugins-tab',
						'label'     => 'Plugin Files',
						'count'     => 2,
						'is_active' => false,
						'target'    => '#h-tabs-plugins',
						'controls'  => 'h-tabs-plugins',
					],
					[
						'key'       => 'vulnerabilities',
						'pane_id'   => 'h-tabs-vulnerabilities',
						'nav_id'    => 'h-tabs-vulnerabilities-tab',
						'label'     => 'Vulnerabilities',
						'count'     => 1,
						'is_active' => false,
						'target'    => '#h-tabs-vulnerabilities',
						'controls'  => 'h-tabs-vulnerabilities',
					],
					[
						'key'       => 'malware',
						'pane_id'   => 'h-tabs-malware',
						'nav_id'    => 'h-tabs-malware-tab',
						'label'     => 'Malware',
						'count'     => 0,
						'is_active' => false,
						'target'    => '#h-tabs-malware',
						'controls'  => 'h-tabs-malware',
					],
				],
				'rail_tabs'       => [
					[
						'key'       => 'summary',
						'pane_id'   => 'h-tabs-summary',
						'nav_id'    => 'h-tabs-summary-tab',
						'label'     => 'Summary',
						'count'     => 3,
						'status'     => 'critical',
						'icon_class' => 'bi bi-clipboard2-pulse-fill',
						'is_active'  => true,
						'target'     => '#h-tabs-summary',
						'controls'  => 'h-tabs-summary',
						'items'     => [
							[
								'title'       => 'Plugin Files',
								'description' => '2 plugin files need review.',
								'status'      => 'warning',
								'count_badge' => 2,
								'attributes'  => [
									'data-shield-rail-switch' => 'plugins',
									'role'                    => 'button',
									'tabindex'                => '0',
								],
							],
						],
					],
					[
						'key'       => 'plugins',
						'pane_id'   => 'h-tabs-plugins',
						'nav_id'    => 'h-tabs-plugins-tab',
						'label'     => 'Plugin Files',
						'count'     => 2,
						'status'     => 'warning',
						'icon_class' => 'bi bi-plug-fill',
						'is_active'  => false,
						'target'     => '#h-tabs-plugins',
						'controls'  => 'h-tabs-plugins',
						'items'     => [
							[
								'title'       => 'Example Plugin',
								'description' => '2 file modifications need review.',
								'status'      => 'warning',
								'count_badge' => 2,
								'expandable'  => true,
								'expand_target' => 'scan-files-plugin-example-plugin',
								'expansion' => [
									'id'     => 'scan-files-plugin-example-plugin',
									'type'   => DetailExpansionType::INVESTIGATION_TABLE,
									'status' => 'warning',
									'table'  => [
										'title'               => 'File Scan Status',
										'status'              => 'warning',
										'table_type'          => 'file_scan_results',
										'subject_type'        => 'plugin',
										'subject_id'          => 'example-plugin/example-plugin.php',
										'datatables_init'     => '{}',
										'table_action'        => '{}',
										'scan_results_action' => '{}',
										'render_item_analysis' => '{}',
										'show_header'         => false,
										'is_flat'             => true,
										'is_empty'            => false,
										'full_log_href'       => '/wp-admin/admin.php?page=shield&nav=scans&subnav=results',
										'full_log_text'       => 'Full Scan Results',
										'full_log_button_class' => 'btn btn-primary btn-sm',
									],
								],
								'actions'     => [
									[
										'type'    => 'deactivate',
										'label'   => 'Deactivate',
										'href'    => '/wp-admin/plugins.php',
										'icon'    => 'bi bi-power',
										'tooltip' => 'Go to plugins',
									],
								],
							],
						],
					],
					[
						'key'       => 'vulnerabilities',
						'pane_id'   => 'h-tabs-vulnerabilities',
						'nav_id'    => 'h-tabs-vulnerabilities-tab',
						'label'     => 'Vulnerabilities',
						'count'     => 1,
						'status'     => 'critical',
						'icon_class' => 'bi bi-shield-exclamation',
						'is_active'  => false,
						'target'     => '#h-tabs-vulnerabilities',
						'controls'  => 'h-tabs-vulnerabilities',
						'items'     => [
							[
								'title'         => 'Example Plugin',
								'description'   => '1 known vulnerability needs review.',
								'status'        => 'critical',
								'count_badge'   => 1,
								'section_label' => 'Known Vulnerabilities',
								'actions'       => [
									[
										'type'  => 'update',
										'label' => 'Go to updates',
										'href'  => '/wp-admin/update-core.php',
										'icon'  => 'bi bi-arrow-up-circle-fill',
									],
									[
										'type'       => 'navigate',
										'label'      => 'Vulnerability Lookup',
										'href'       => 'https://lookup.example/plugin',
										'icon'       => 'bi bi-box-arrow-up-right',
										'attributes' => [
											'target' => '_blank',
											'rel'    => 'noopener noreferrer',
										],
									],
								],
							],
						],
					],
					[
						'key'       => 'malware',
						'pane_id'   => 'h-tabs-malware',
						'nav_id'    => 'h-tabs-malware-tab',
						'label'     => 'Malware',
						'count'     => 0,
						'status'     => 'good',
						'icon_class' => 'bi bi-bug-fill',
						'is_active'  => false,
						'target'     => '#h-tabs-malware',
						'controls'  => 'h-tabs-malware',
						'items'     => [],
					],
				],
				'summary_rows'    => [
					[
						'severity'    => 'warning',
						'label'       => 'Plugin Files',
						'description' => '2 plugin files need review.',
						'count'       => 2,
					],
				],
				'assessment_rows' => [],
				'vulnerabilities' => [
					'count'    => 1,
					'status'   => 'critical',
					'sections' => [
						[
							'label' => 'Known Vulnerabilities',
							'items' => [
								[
									'severity'    => 'critical',
									'label'       => 'Example Plugin',
									'description' => '1 known vulnerability needs review.',
									'count'       => 1,
									'cta'         => [
										'href'  => '/wp-admin/update-core.php',
										'label' => 'Go to updates',
										'type'  => 'update',
									],
								],
							],
						],
					],
				],
			],
			'content' => [
				'section' => [
					'plugins'    => '<div id="PluginsPaneBody">Plugins content</div>',
					'malware'    => '<div id="MalwarePaneBody">Malware content</div>',
					'wordpress'  => '',
					'themes'     => '',
					'filelocker' => '',
				],
			],
		];

		$context[ 'vars' ][ 'rail_tabs' ] = $this->normalizeRailTabs( $context[ 'vars' ][ 'rail_tabs' ] );

		return $context;
	}

	private function buildLazyPaneRenderContext() :array {
		$context = [
			'vars'    => [
				'rail'      => [
					'id'            => 'ScanResultsRailSidebar',
					'accent_status' => 'warning',
					'items'         => [
						[
							'key'       => 'summary',
							'label'     => 'Summary',
							'status'    => 'good',
							'count'     => 1,
							'nav_id'    => 'h-tabs-summary-tab',
							'target'    => '#h-tabs-summary',
							'controls'  => 'h-tabs-summary',
							'is_active' => true,
						],
						[
							'key'       => 'plugins',
							'label'     => 'Plugin Files',
							'status'    => 'neutral',
							'count'     => null,
							'show_count_placeholder' => true,
							'nav_id'    => 'h-tabs-plugins-tab',
							'target'    => '#h-tabs-plugins',
							'controls'  => 'h-tabs-plugins',
							'is_active' => false,
						],
					],
				],
				'rail_tabs' => [
					[
						'key'        => 'summary',
						'pane_id'    => 'h-tabs-summary',
						'nav_id'     => 'h-tabs-summary-tab',
						'label'      => 'Summary',
						'status'     => 'good',
						'is_active'  => true,
						'is_loaded'  => true,
						'items'      => [
							[
								'title'       => 'Plugin Files',
								'description' => '1 plugin needs review.',
								'status'      => 'warning',
							],
						],
					],
					[
						'key'           => 'plugins',
						'pane_id'       => 'h-tabs-plugins',
						'nav_id'        => 'h-tabs-plugins-tab',
						'label'         => 'Plugin Files',
						'status'        => 'neutral',
						'is_active'     => false,
						'is_loaded'     => false,
						'render_action' => [
							'render_slug' => 'scanresults_plugins',
						],
						'items'         => [],
					],
				],
			],
			'strings' => [
				'pane_loading' => 'Loading scan details...',
				'no_issues'    => 'No issues found in this section.',
			],
			'content' => [],
		];

		$context[ 'vars' ][ 'rail_tabs' ] = $this->normalizeRailTabs( $context[ 'vars' ][ 'rail_tabs' ] );

		return $context;
	}

	private function buildDisabledPaneRenderContext() :array {
		return [
			'strings' => [
				'no_issues' => 'No issues found in this section.',
				'pane_loading' => 'Loading scan details...',
			],
			'tab'     => $this->normalizeRailTab( [
				'key'              => 'malware',
				'pane_id'          => 'h-tabs-malware',
				'is_loaded'        => true,
				'is_disabled'      => true,
				'disabled_message' => 'Malware Scanning is not enabled.',
				'items'            => [],
			] ),
			'content' => [],
		];
	}

	private function buildMaintenancePaneRenderContext() :array {
		return [
			'strings' => [
				'no_issues'    => 'No issues found in this section.',
				'pane_loading' => 'Loading scan details...',
			],
			'tab'     => $this->normalizeRailTab( [
				'key'       => 'maintenance',
				'pane_id'   => 'h-tabs-maintenance',
				'is_loaded' => true,
				'render_action' => [
					'render_slug' => 'scanresults_maintenance',
				],
				'items'     => [
					[
						'title'         => 'Plugins With Updates',
						'description'   => '1 plugin update is available.',
						'status'        => 'warning',
						'count_badge'   => 1,
						'expandable'    => true,
						'expand_target' => 'maintenance-expand-wp_plugins_updates',
						'expansion'     => [
							'id'     => 'maintenance-expand-wp_plugins_updates',
							'type'   => DetailExpansionType::SIMPLE_TABLE,
							'status' => 'warning',
							'table'  => [
								'columns' => [
									'item'    => 'Item',
									'details' => 'Details',
									'action'  => 'Action',
								],
								'rows' => [
									[
										'title'             => 'Akismet Anti-Spam',
										'subtitle'          => 'Plugin update available',
										'context'           => 'Current: 5.3.0 | Available: 5.4.0',
										'identifier'        => 'akismet/akismet.php',
										'action'            => [
											'label' => 'Update',
											'href'  => '/wp-admin/update.php?action=upgrade-plugin&plugin=akismet/akismet.php',
										],
										'is_ignored'        => false,
										'ignored_label'     => '',
										'secondary_actions' => [
											[
												'label'       => 'Ignore',
												'href'        => 'javascript:{}',
												'icon'        => 'bi bi-eye-slash-fill',
												'tooltip'     => 'Ignore this maintenance item',
												'ajax_action' => [
													'ex' => 'maintenance_item_ignore',
												],
											],
										],
									],
									[
										'title'             => 'Hello Dolly',
										'subtitle'          => 'Plugin update available',
										'context'           => 'Current: 1.7.2 | Available: 1.8.0',
										'identifier'        => 'hello-dolly/hello.php',
										'action'            => [
											'label' => 'Update',
											'href'  => '/wp-admin/update.php?action=upgrade-plugin&plugin=hello-dolly/hello.php',
										],
										'is_ignored'        => true,
										'ignored_label'     => 'Currently ignored',
										'secondary_actions' => [
											[
												'label'       => 'Stop ignoring',
												'href'        => 'javascript:{}',
												'icon'        => 'bi bi-eye-fill',
												'tooltip'     => 'Stop ignoring this maintenance item',
												'ajax_action' => [
													'ex' => 'maintenance_item_unignore',
												],
											],
										],
									],
								],
								'empty_text' => 'No items are currently available.',
							],
						],
						'actions'       => [
							[
								'type'  => 'navigate',
								'label' => 'Update',
								'href'  => '/wp-admin/update-core.php',
								'icon'  => 'bi bi-arrow-right-circle-fill',
							],
						],
					],
					[
						'title'       => 'PHP Version',
						'description' => 'This maintenance item is currently ignored.',
						'status'      => 'good',
						'actions'     => [
							[
								'type'  => 'navigate',
								'label' => 'Open',
								'href'  => '/wp-admin/update-core.php',
								'icon'  => 'bi bi-arrow-right-circle-fill',
							],
							[
								'type'       => 'navigate',
								'label'      => 'Stop ignoring',
								'href'       => 'javascript:{}',
								'icon'       => 'bi bi-eye-fill',
								'tooltip'    => 'Stop ignoring this maintenance item',
								'attributes' => [
									'data-actions-queue-maintenance-action' => '{"ex":"maintenance_item_unignore"}',
								],
							],
						],
					],
				],
			] ),
			'content' => [],
		];
	}

	private function buildActionsQueuePluginCardsContext() :array {
		return [
			'flags'   => [
				'is_disabled' => false,
			],
			'strings' => [
				'no_issues'         => 'No issues found in this section.',
				'select_asset_hint' => 'Select an item above to review it.',
			],
			'vars'    => [
				'asset_cards' => [
					[
						'key'          => 'example-plugin',
						'panel_id'     => 'actions-queue-plugin-card-example-plugin',
						'panel_target' => 'actions-queue-plugin-example-plugin',
						'status'       => 'warning',
						'icon_class'   => 'bi bi-plug-fill',
						'title'        => 'Example Plugin',
						'stat_text'    => '2 files need review',
						'meta_text'    => 'example-plugin/example-plugin.php',
						'show_meta_in_tile' => true,
						'count_badge'  => 2,
						'actions'      => [
							[
								'type'    => 'deactivate',
								'label'   => 'Deactivate',
								'href'    => '/wp-admin/plugins.php',
								'icon'    => 'bi bi-power',
								'tooltip' => 'Go to plugins',
								'attributes' => [],
							],
						],
						'table'       => [
							'title'                 => 'File Scan Status',
							'status'                => 'warning',
							'table_type'            => 'file_scan_results',
							'subject_type'          => 'plugin',
							'subject_id'            => 'example-plugin/example-plugin.php',
							'datatables_init'       => '{}',
							'table_action'          => '{}',
							'scan_results_action'   => '{}',
							'render_item_analysis'  => '{}',
							'show_header'           => false,
							'is_flat'               => true,
							'is_empty'              => false,
							'full_log_href'         => '/wp-admin/admin.php?page=shield&nav=scans&subnav=results',
							'full_log_text'         => 'Full Scan Results',
							'full_log_button_class' => 'btn btn-primary btn-sm',
						],
						'render_action' => [],
					],
				],
			],
		];
	}

	private function buildActionsQueueFileLockerCardsContext() :array {
		return [
			'flags'   => [
				'is_disabled' => false,
			],
			'strings' => [
				'no_issues'         => 'No issues found in this section.',
				'select_asset_hint' => 'Select an item above to review it.',
			],
			'vars'    => [
				'asset_cards' => [
					[
						'key'           => '14',
						'panel_id'      => 'actions-queue-filelocker-card-14',
						'panel_target'  => 'actions-queue-filelocker-14',
						'status'        => 'good',
						'icon_class'    => 'bi bi-file-lock2-fill',
						'title'         => 'wp-config.php',
						'stat_text'     => 'File integrity verified.',
						'meta_text'     => '/wp-config.php',
						'show_meta_in_tile' => false,
						'count_badge'   => null,
						'actions'       => [],
						'table'         => [],
						'render_action' => [
							'render_slug' => 'filelocker_showdiff',
							'rid'         => 14,
						],
					],
				],
			],
		];
	}

	private function buildFileLockerDiffContext() :array {
		return [
			'success' => true,
			'flags'   => [
				'has_diff'               => true,
				'original_file_missing'  => false,
				'current_content_empty'  => false,
			],
			'strings' => [
				'reviewing_locked_file' => 'Reviewing Locked File',
				'file_content_original' => 'Original',
				'file_content_current'  => 'Current',
				'file_details'          => 'File Details',
				'relative_path'         => 'Relative Path',
				'locked'                => 'Locked',
				'file_size'             => 'File Size',
				'file_modified'         => 'File Modified',
				'download'              => 'Download',
				'modified_file'         => 'Modified File',
				'change_detected_at'    => 'Change Detected At',
				'file_restore'          => 'Restore File',
				'file_restore_checkbox' => 'Confirm restore',
				'butt_restore'          => 'Restore',
				'file_accept'           => 'Accept File',
				'file_accept_checkbox'  => 'Confirm accept',
				'butt_accept'           => 'Accept',
			],
			'vars'    => [
				'rid'                => 14,
				'full_path'          => '/srv/www/wp-config.php',
				'relative_path'      => 'wp-config.php',
				'locked_at'          => '2026-03-12 08:00:00',
				'file_size_locked'   => '2 KB',
				'file_modified_ago'  => '2 minutes ago',
				'file_modified_at'   => '2026-03-12 08:05:00',
				'change_detected_at' => '2026-03-12 08:06:00',
				'file_size_modified' => '3 KB',
			],
			'html'    => [
				'diff' => '<div>Diff</div>',
			],
			'ajax'    => [
				'original' => '/download/original',
				'current'  => '/download/current',
			],
		];
	}

	public function testScanResultsTemplatesCompileWithTwigParser() :void {
		$twig = $this->twig();

		foreach ( [
			'/wpadmin_pages/insights/scans/results/scan_results.twig',
			'/wpadmin_pages/insights/scans/results/scan_results_rail.twig',
			'/wpadmin_pages/insights/scans/results/scan_results_rail_pane.twig',
			'/wpadmin_pages/insights/scans/results/scan_results_pane_body.twig',
			'/wpadmin_pages/insights/scans/results/actions_queue_asset_cards.twig',
		] as $templatePath ) {
			try {
				$template = $twig->load( $templatePath );
			}
			catch ( \Throwable $e ) {
				$this->fail(
					\sprintf(
						'Failed compiling %s. %s: %s',
						$templatePath,
						\get_class( $e ),
						$e->getMessage()
					)
				);
				return;
			}

			$this->assertInstanceOf( TemplateWrapper::class, $template );
		}
	}

	public function testBootstrapScanResultsTemplateKeepsLegacyTabWrapper() :void {
		$html = $this->twig()->render(
			'/wpadmin_pages/insights/scans/results/scan_results.twig',
			$this->buildRenderContext()
		);
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//*[@id="ScanResultsTabsNav"]',
			'Bootstrap scans results template should keep the legacy tab nav'
		);
		$this->assertSame(
			0,
			$xpath->query( '//*[@data-shield-rail-scope="1"]' )->length,
			'Bootstrap scans results template should not render the rail scope marker'
		);
	}

	public function testRailScanResultsTemplateDerivesCriticalAccentAndBootstrapTabContracts() :void {
		$html = $this->twig()->render(
			'/wpadmin_pages/insights/scans/results/scan_results_rail.twig',
			$this->buildRenderContext()
		);
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " shield-rail-sidebar__accent--critical ")]',
			'Rail scans results template should render the PHP-owned rail accent status'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-scope="1"]',
			'Rail scans results template should render a scoped rail layout'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-target="summary" and @data-bs-toggle="tab" and @aria-selected="true"]',
			'Summary rail item should render a Bootstrap tab trigger'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-target="summary" and @data-shield-rail-status="critical"]',
			'Rail scans results template should expose the summary status on the shared rail trigger'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-target="summary"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-rail-sidebar__icon ")]/i[contains(concat(" ", normalize-space(@class), " "), " bi-clipboard2-pulse-fill ")]',
			'Summary rail item should render its icon in the rail trigger'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-scope="1"]//*[contains(concat(" ", normalize-space(@class), " "), " tab-content ")]/*[@data-shield-rail-pane="summary"]',
			'Rail scans results panes should render inside a Bootstrap tab-content container'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="plugins"]',
			'Rail scans results should render pane contracts for non-summary tabs'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="vulnerabilities"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-mode-strip__eyebrow ")]',
			'Rail pane should render section headers for grouped vulnerability items'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="summary"]//*[@data-shield-rail-switch="plugins" and @role="button" and @tabindex="0"]',
			'Summary pane issue rows should render row-level rail switch attributes instead of a separate action chip'
		);
		$this->assertSame(
			0,
			$xpath->query( '//*[@data-shield-rail-pane="summary"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-action-chip ")]' )->length,
			'Summary pane issue rows should not render separate action chips'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="plugins"]//*[@data-shield-expand-trigger="1" and @data-shield-expand-target="scan-files-plugin-example-plugin"]',
			'Plugin pane rows should render inline expansion triggers for changed files'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-expand-trigger="1" and @data-shield-expand-target="scan-files-plugin-example-plugin"]/ancestor::div[contains(concat(" ", normalize-space(@class), " "), " shield-detail-item ")][1]//*[@id="scan-files-plugin-example-plugin"]',
			'Plugin pane expansions should stay nested inside the shared detail item wrapper'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="scan-files-plugin-example-plugin"]//*[@data-investigation-table="1" and @data-table-type="file_scan_results" and @data-subject-type="plugin" and @data-subject-id="example-plugin/example-plugin.php"]',
			'Plugin pane expansions should render the shared investigation file scan table contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="scan-files-plugin-example-plugin" and contains(concat(" ", normalize-space(@class), " "), " collapse ") and @data-bs-parent="#h-tabs-plugins"]',
			'Plugin pane expansions should render Bootstrap collapse contracts inside the pane'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="vulnerabilities"]//a[@href="/wp-admin/update-core.php" and contains(concat(" ", normalize-space(@class), " "), " shield-action-chip--update ")]',
			'Vulnerability pane should render native WordPress update actions instead of Investigate links'
		);
		$this->assertSame(
			0,
			$xpath->query( '//*[@data-shield-rail-pane="vulnerabilities"]//a[@href="/shield/investigate/plugin#tab-navlink-plugin-vulnerabilities"]' )->length,
			'Vulnerability pane should not render the removed investigate deeplink action'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="vulnerabilities"]//a[@href="https://lookup.example/plugin" and @target="_blank" and @rel="noopener noreferrer"]',
			'Vulnerability pane should render the external vulnerability lookup action'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="malware"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-scan-pane-empty ")]',
			'Clean rail panes should render an empty-state message'
		);
		$this->assertSame(
			0,
			$xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " shield-pane-header ")]' )->length,
			'Rail panes should not render a duplicate pane header'
		);
	}

	public function testRailScanResultsTemplateKeepsLazyPanesUnloadedUntilAjaxHydratesThem() :void {
		$html = $this->twig()->render(
			'/wpadmin_pages/insights/scans/results/scan_results_rail.twig',
			$this->buildLazyPaneRenderContext()
		);
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="plugins" and @data-actions-queue-pane-loaded="0"]',
			'Lazy scan panes should stay marked unloaded in the initial rail shell'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-target="plugins" and @data-shield-rail-status="neutral"]',
			'Lazy scan tabs should expose their neutral pre-hydration status on the shared rail trigger'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-target="plugins"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-rail-sidebar__badge--placeholder ") and normalize-space()="-"]',
			'Lazy scan tabs should render a stable placeholder badge before counts hydrate'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="plugins"]//*[@data-actions-queue-pane-placeholder="1" and normalize-space()="Loading scan details..."]',
			'Lazy scan panes should render the loading placeholder before AJAX hydration'
		);
		$this->assertSame(
			0,
			$xpath->query( '//*[@data-shield-rail-pane="plugins"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-scan-pane-empty ")]' )->length,
			'Lazy scan panes should not render the empty-state before AJAX hydration'
		);
	}

	public function testRailPaneTemplateRendersDisabledStateBeforeEmptyState() :void {
		$html = $this->twig()->render(
			'/wpadmin_pages/insights/scans/results/scan_results_rail_pane.twig',
			$this->buildDisabledPaneRenderContext()
		);
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-scan-pane-disabled="1" and contains(concat(" ", normalize-space(@class), " "), " shield-scan-pane-disabled ") and contains(normalize-space(), "Malware Scanning is not enabled.")]',
			'Disabled scan panes should render the disabled-state callout'
		);
		$this->assertSame(
			0,
			$xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " shield-scan-pane-empty ")]' )->length,
			'Disabled scan panes should not fall through to the generic empty-state'
		);
	}

	public function testRailPaneTemplateRendersSharedSimpleTableExpansionForMaintenanceRows() :void {
		$html = $this->twig()->render(
			'/wpadmin_pages/insights/scans/results/scan_results_rail_pane.twig',
			$this->buildMaintenancePaneRenderContext()
		);
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-expand-trigger="1" and @data-shield-expand-target="maintenance-expand-wp_plugins_updates"]',
			'Maintenance pane rows should reuse the shared expandable detail row trigger'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="maintenance-expand-wp_plugins_updates" and contains(concat(" ", normalize-space(@class), " "), " collapse ") and @data-bs-parent="#h-tabs-maintenance"]',
			'Maintenance pane rows should reuse the shared Bootstrap collapse shell'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="maintenance-expand-wp_plugins_updates"]//table[contains(concat(" ", normalize-space(@class), " "), " table-sm ")]//tr[td//*[normalize-space()="Akismet Anti-Spam"]]',
			'Maintenance pane expansions should render the shared simple table partial'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="maintenance-expand-wp_plugins_updates"]//a[@href="/wp-admin/update.php?action=upgrade-plugin&plugin=akismet/akismet.php" and normalize-space()="Update"]',
			'Maintenance pane simple tables should render per-row actions'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="maintenance-expand-wp_plugins_updates"]//*[@data-actions-queue-maintenance-action and @data-bs-toggle="tooltip"]',
			'Maintenance pane simple tables should render maintenance ignore toggle buttons with tooltips'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="maintenance-expand-wp_plugins_updates"]//tr[@data-actions-queue-maintenance-ignored="1"]',
			'Maintenance pane simple tables should mark ignored rows for muted presentation'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " shield-detail-row ")]'
			.'[.//*[contains(concat(" ", normalize-space(@class), " "), " shield-detail-row__title ") and normalize-space()="PHP Version"]]'
			.'//a[@href="javascript:{}" and contains(concat(" ", normalize-space(@class), " "), " shield-action-chip ")]'
			.'[.//i[contains(concat(" ", normalize-space(@class), " "), " bi-eye-fill ")]]',
			'Maintenance pane singleton rows should render maintenance unignore action chips'
		);
		$this->assertSame(
			0,
			$xpath->query( '//*[@id="maintenance-expand-wp_plugins_updates"]//*[@data-investigation-table="1"]' )->length,
			'Maintenance pane simple table expansions should not render the investigation table contract'
		);
	}

	public function testActionsQueueAssetCardsTemplateRendersModeTilesWithInlinePluginTablesAndPerFileLockerPanels() :void {
		$pluginHtml = $this->twig()->render(
			'/wpadmin_pages/insights/scans/results/actions_queue_asset_cards.twig',
			$this->buildActionsQueuePluginCardsContext()
		);
		$pluginXpath = $this->createDomXPathFromHtml( $pluginHtml );

		$this->assertXPathExists(
			$pluginXpath,
			'//*[@data-actions-queue-asset-cards="1" and @data-mode-shell="1" and @data-mode="actions_queue_assets" and @data-mode-interactive="1"]',
			'Queue asset cards template should render a nested interactive mode shell'
		);
		$this->assertXPathExists(
			$pluginXpath,
			'//*[@data-mode-tile="1" and @data-mode-panel-target="actions-queue-plugin-example-plugin"]',
			'Queue asset cards template should render plugin mode tiles'
		);
		$this->assertXPathExists(
			$pluginXpath,
			'//*[@id="actions-queue-plugin-card-example-plugin" and @data-mode-panel="1" and @data-mode-panel-target="actions-queue-plugin-example-plugin"]//*[@data-investigation-table="1" and @data-subject-type="plugin" and @data-subject-id="example-plugin/example-plugin.php"]',
			'Queue plugin cards should render the unchanged investigation table contract'
		);
		$this->assertXPathExists(
			$pluginXpath,
			'//*[@data-mode-tile="1" and @data-mode-panel-target="actions-queue-plugin-example-plugin"]//*[contains(concat(" ", normalize-space(@class), " "), " actions-queue-asset-card__meta ") and normalize-space()="example-plugin/example-plugin.php"]',
			'Queue plugin cards should continue rendering top-tile meta text by default'
		);

		$fileLockerHtml = $this->twig()->render(
			'/wpadmin_pages/insights/scans/results/actions_queue_asset_cards.twig',
			$this->buildActionsQueueFileLockerCardsContext()
		);
		$fileLockerXpath = $this->createDomXPathFromHtml( $fileLockerHtml );

		$this->assertXPathExists(
			$fileLockerXpath,
			'//*[@id="actions-queue-filelocker-card-14" and @data-mode-panel="1" and @data-mode-panel-target="actions-queue-filelocker-14" and contains(@data-actions-queue-asset-render-action, "filelocker_showdiff")]',
			'Queue File Locker cards should render one mode panel per file with the existing diff render action'
		);
		$this->assertXPathExists(
			$fileLockerXpath,
			'//*[@id="actions-queue-filelocker-card-14"]//*[@data-actions-queue-asset-panel-content="1"]',
			'Queue File Locker cards should render a panel-scoped lazy content target'
		);
		$this->assertSame(
			0,
			$fileLockerXpath->query( '//*[@data-mode-tile="1" and @data-mode-panel-target="actions-queue-filelocker-14"]//*[contains(concat(" ", normalize-space(@class), " "), " actions-queue-asset-card__meta ")]' )->length,
			'Queue File Locker cards should not render the full path on the top tile'
		);
		$this->assertXPathExists(
			$fileLockerXpath,
			'//*[@id="actions-queue-filelocker-card-14"]//*[contains(concat(" ", normalize-space(@class), " "), " actions-queue-asset-panel__meta ") and normalize-space()="/wp-config.php"]',
			'Queue File Locker cards should keep the full path inside the panel summary'
		);
	}

	public function testFileLockerDiffTemplateUsesRecordScopedCheckboxIds() :void {
		$html = '<div>'.$this->twig()->render(
			'/wpadmin_pages/insights/scans/results/realtime/file_locker/file_diff.twig',
			$this->buildFileLockerDiffContext()
		).'</div>';
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//input[@id="ConfirmFileRestore-14" and @name="ConfirmFileRestore"]',
			'File Locker diff restore confirmation should scope its checkbox ID to the record'
		);
		$this->assertXPathExists(
			$xpath,
			'//label[@for="ConfirmFileRestore-14"]',
			'File Locker diff restore label should target the scoped restore checkbox ID'
		);
		$this->assertXPathExists(
			$xpath,
			'//input[@id="ConfirmFileAccept-14" and @name="ConfirmFileAccept"]',
			'File Locker diff accept confirmation should scope its checkbox ID to the record'
		);
		$this->assertXPathExists(
			$xpath,
			'//label[@for="ConfirmFileAccept-14"]',
			'File Locker diff accept label should target the scoped accept checkbox ID'
		);
		$this->assertSame(
			0,
			$xpath->query( '//input[@id="ConfirmFileRestore" or @id="ConfirmFileAccept"]' )->length,
			'File Locker diff template should no longer render global fixed confirmation checkbox IDs'
		);
	}
}
