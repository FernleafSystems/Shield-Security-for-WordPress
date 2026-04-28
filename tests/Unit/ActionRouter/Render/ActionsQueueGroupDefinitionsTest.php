<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	FileLocker,
	Malware,
	Maintenance,
	Vulnerabilities,
	Wordpress
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueGroupDefinitions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActionsQueueGroupDefinitionsTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
	}

	public function test_definitions_reuse_existing_scan_metadata_and_render_contracts() :void {
		$definitions = ( new ActionsQueueGroupDefinitions() )->all();

		$this->assertSame( 'WordPress Files', $definitions[ 'wordpress' ][ 'label' ] );
		$this->assertSame( 'direct_table', $definitions[ 'wordpress' ][ 'detail_shell' ] );
		$this->assertSame( 'expandable', $definitions[ 'wordpress' ][ 'card_type' ] );
		$this->assertSame( Wordpress::class, $definitions[ 'wordpress' ][ 'render_action_class' ] );
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
			$definitions[ 'wordpress' ][ 'render_action_data' ]
		);
		$this->assertSame( 'Malware Detections', $definitions[ 'malware' ][ 'label' ] );
		$this->assertSame( 'direct_table', $definitions[ 'malware' ][ 'detail_shell' ] );
		$this->assertSame( 'linked', $definitions[ 'vulnerabilities' ][ 'card_type' ] );
		$this->assertSame( 'Abandoned Assets', $definitions[ 'abandoned' ][ 'label' ] );
		$this->assertSame( 'linked', $definitions[ 'abandoned' ][ 'card_type' ] );
		$this->assertSame( Malware::class, $definitions[ 'malware' ][ 'render_action_class' ] );
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
			$definitions[ 'malware' ][ 'render_action_data' ]
		);
		$this->assertSame( 'asset_cards', $definitions[ 'plugins' ][ 'detail_shell' ] );
		$this->assertSame( 'asset_cards', $definitions[ 'themes' ][ 'detail_shell' ] );
		$this->assertSame( Vulnerabilities::class, $definitions[ 'vulnerabilities' ][ 'render_action_class' ] );
		$this->assertSame( [ 'section' => 'vulnerable' ], $definitions[ 'vulnerabilities' ][ 'render_action_data' ] );
		$this->assertSame( Vulnerabilities::class, $definitions[ 'abandoned' ][ 'render_action_class' ] );
		$this->assertSame( [ 'section' => 'abandoned' ], $definitions[ 'abandoned' ][ 'render_action_data' ] );
		$this->assertSame( 'asset_cards', $definitions[ 'file_locker' ][ 'detail_shell' ] );
		$this->assertSame( FileLocker::class, $definitions[ 'file_locker' ][ 'render_action_class' ] );
		$this->assertSame( 'maintenance', $definitions[ 'maintenance' ][ 'detail_shell' ] );
		$this->assertSame( 'category', $definitions[ 'maintenance' ][ 'card_type' ] );
		$this->assertSame( Maintenance::class, $definitions[ 'maintenance' ][ 'render_action_class' ] );
		$this->assertSame( 'Maintenance Items', $definitions[ 'maintenance' ][ 'label' ] );
		$this->assertSame( 'System', $definitions[ 'maintenance_system' ][ 'label' ] );
		$this->assertSame( 'bi bi-shield-lock-fill', $definitions[ 'maintenance_system' ][ 'icon_class' ] );
		$this->assertSame( 'maintenance', $definitions[ 'maintenance_system' ][ 'detail_shell' ] );
		$this->assertSame( 'category', $definitions[ 'maintenance_system' ][ 'card_type' ] );
		$this->assertSame( Maintenance::class, $definitions[ 'maintenance_system' ][ 'render_action_class' ] );
		$this->assertSame( 'WordPress', $definitions[ 'maintenance_wordpress' ][ 'label' ] );
		$this->assertSame( 'bi bi-wordpress', $definitions[ 'maintenance_wordpress' ][ 'icon_class' ] );
		$this->assertSame( 'maintenance', $definitions[ 'maintenance_wordpress' ][ 'detail_shell' ] );
		$this->assertSame( 'category', $definitions[ 'maintenance_wordpress' ][ 'card_type' ] );
		$this->assertSame( Maintenance::class, $definitions[ 'maintenance_wordpress' ][ 'render_action_class' ] );
	}

	public function test_definitions_own_group_section_metadata_for_fix_now_ordering() :void {
		$definitions = new ActionsQueueGroupDefinitions();

		$this->assertSame( 'wordpress', $definitions->sectionKeyForGroupKey( 'wordpress' ) );
		$this->assertSame( 'wordpress', $definitions->sectionKeyForGroupKey( 'malware' ) );
		$this->assertSame( 'wordpress', $definitions->sectionKeyForGroupKey( 'file_locker' ) );
		$this->assertSame( 0, $definitions->sectionOrderForGroupKey( 'wordpress' ) );
		$this->assertSame( 0, $definitions->sectionOrderForGroupKey( 'malware' ) );
		$this->assertSame( 0, $definitions->sectionOrderForGroupKey( 'file_locker' ) );
		$this->assertSame( 'File Integrity', $definitions->sectionLabelForGroupKey( 'wordpress' ) );
		$this->assertSame( 'File Integrity', $definitions->sectionLabelForGroupKey( 'malware' ) );
		$this->assertSame( 'File Integrity', $definitions->sectionLabelForGroupKey( 'file_locker' ) );

		$this->assertSame( 1, $definitions->sectionOrderForGroupKey( 'vulnerabilities' ) );
		$this->assertSame( 2, $definitions->sectionOrderForGroupKey( 'plugins' ) );
		$this->assertSame( 3, $definitions->sectionOrderForGroupKey( 'themes' ) );
		$this->assertSame( 4, $definitions->sectionOrderForGroupKey( 'abandoned' ) );
		$this->assertSame( 'Known Vulnerabilities', $definitions->sectionLabelForGroupKey( 'vulnerabilities' ) );
		$this->assertSame( 'Plugins with modified files', $definitions->sectionLabelForGroupKey( 'plugins' ) );
		$this->assertSame( 'Themes with modified files', $definitions->sectionLabelForGroupKey( 'themes' ) );
		$this->assertSame( 'Abandoned Assets', $definitions->sectionLabelForGroupKey( 'abandoned' ) );

		$this->assertSame( 2, $definitions->sortOrderForGroupKey( 'wordpress' ) );
		$this->assertSame( 5, $definitions->sortOrderForGroupKey( 'malware' ) );
		$this->assertSame( 6, $definitions->sortOrderForGroupKey( 'file_locker' ) );
		$this->assertSame( '', $definitions->sectionLabelForGroupKey( 'maintenance' ) );
	}

	public function test_summary_keys_map_back_to_canonical_group_keys() :void {
		$definitions = new ActionsQueueGroupDefinitions();

		$this->assertSame( 'wordpress', $definitions->groupKeyForSummaryKey( 'wp_files' ) );
		$this->assertSame( 'wordpress', $definitions->groupKeyForSummaryKey( 'wp_files_ignored' ) );
		$this->assertSame( 'plugins', $definitions->groupKeyForSummaryKey( 'plugin_files' ) );
		$this->assertSame( 'plugins', $definitions->groupKeyForSummaryKey( 'plugin_files_ignored' ) );
		$this->assertSame( 'themes', $definitions->groupKeyForSummaryKey( 'theme_files' ) );
		$this->assertSame( 'themes', $definitions->groupKeyForSummaryKey( 'theme_files_ignored' ) );
		$this->assertSame( 'vulnerabilities', $definitions->groupKeyForSummaryKey( 'vulnerable_assets' ) );
		$this->assertSame( 'abandoned', $definitions->groupKeyForSummaryKey( 'abandoned' ) );
		$this->assertSame( 'malware', $definitions->groupKeyForSummaryKey( 'malware' ) );
		$this->assertSame( 'malware', $definitions->groupKeyForSummaryKey( 'malware_ignored' ) );
		$this->assertSame( 'file_locker', $definitions->groupKeyForSummaryKey( 'file_locker' ) );
		$this->assertSame( 'maintenance', $definitions->groupKeyForSummaryKey( 'wp_updates' ) );
	}

	public function test_review_maintenance_mapping_is_scoped_to_review_grouping_only() :void {
		$definitions = new ActionsQueueGroupDefinitions();

		$this->assertSame( 'maintenance_system', $definitions->reviewMaintenanceGroupKeyForItemKey( 'system_lib_openssl' ) );
		$this->assertSame( 'maintenance_system', $definitions->reviewMaintenanceGroupKeyForItemKey( 'system_ssl_certificate' ) );
		$this->assertSame( 'maintenance_system', $definitions->reviewMaintenanceGroupKeyForItemKey( 'system_php_version' ) );
		$this->assertSame( 'maintenance_wordpress', $definitions->reviewMaintenanceGroupKeyForItemKey( 'wp_updates' ) );
		$this->assertSame( 'maintenance_wordpress', $definitions->reviewMaintenanceGroupKeyForItemKey( 'wp_db_password' ) );
		$this->assertSame( 'maintenance_wordpress', $definitions->reviewMaintenanceGroupKeyForItemKey( 'default_admin_user' ) );
		$this->assertSame( 'wp_plugins_updates', $definitions->reviewMaintenanceGroupKeyForItemKey( 'wp_plugins_updates' ) );
		$this->assertTrue( $definitions->isReviewMaintenanceAggregateGroupKey( 'maintenance_system' ) );
		$this->assertTrue( $definitions->isReviewMaintenanceAggregateGroupKey( 'maintenance_wordpress' ) );
		$this->assertFalse( $definitions->isReviewMaintenanceAggregateGroupKey( 'maintenance' ) );
	}

	public function test_summary_behaviour_and_healthy_ignored_metadata_are_centralized() :void {
		$definitions = new ActionsQueueGroupDefinitions();

		$this->assertSame(
			[
				'definition_key' => 'plugins',
				'seed_strategy'  => 'asset_cards',
				'asset_source'   => 'plugins',
			],
			$definitions->summaryBehaviourForKey( 'plugin_files' )
		);
		$this->assertSame(
			[
				'definition_key' => 'plugins',
				'seed_strategy'  => 'asset_cards',
				'asset_source'   => 'plugins',
			],
			$definitions->summaryBehaviourForKey( 'plugin_files_ignored' )
		);
		$this->assertSame(
			[
				'definition_key' => 'themes',
				'seed_strategy'  => 'asset_cards',
				'asset_source'   => 'themes',
			],
			$definitions->summaryBehaviourForKey( 'theme_files_ignored' )
		);
		$this->assertSame(
			[
				'definition_key'        => 'abandoned',
				'seed_strategy'         => 'vulnerability_section',
				'vulnerability_section' => 'abandoned',
			],
			$definitions->summaryBehaviourForKey( 'abandoned' )
		);
		$this->assertSame( 'themes', $definitions->healthyIgnoredSourceForGroupKey( 'themes' ) );
		$this->assertSame( 'malware', $definitions->healthyIgnoredSourceForGroupKey( 'malware' ) );
		$this->assertSame( 'wordpress', $definitions->healthyIgnoredSourceForGroupKey( 'wordpress' ) );
	}

	public function test_ignored_only_summary_key_helpers_are_centralized() :void {
		$this->assertSame(
			[ 'wp_files_ignored', 'plugin_files_ignored', 'theme_files_ignored', 'malware_ignored' ],
			ActionsQueueGroupDefinitions::ignoredOnlySummaryKeys()
		);
		$this->assertTrue( ActionsQueueGroupDefinitions::isIgnoredOnlySummaryKey( 'theme_files_ignored' ) );
		$this->assertFalse( ActionsQueueGroupDefinitions::isIgnoredOnlySummaryKey( 'theme_files' ) );
	}
}
