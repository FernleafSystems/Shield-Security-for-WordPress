<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	FileLocker,
	Maintenance,
	Malware,
	Vulnerabilities,
	Wordpress
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueGroupDefinitions;
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
		$this->assertSame( [ 'display_context' => 'actions_queue' ], $definitions[ 'wordpress' ][ 'render_action_data' ] );
		$this->assertSame( 'Malware Detections', $definitions[ 'malware' ][ 'label' ] );
		$this->assertSame( 'direct_table', $definitions[ 'malware' ][ 'detail_shell' ] );
		$this->assertSame( 'linked', $definitions[ 'vulnerabilities' ][ 'card_type' ] );
		$this->assertSame( Malware::class, $definitions[ 'malware' ][ 'render_action_class' ] );
		$this->assertSame( 'asset_cards', $definitions[ 'plugins' ][ 'detail_shell' ] );
		$this->assertSame( 'asset_cards', $definitions[ 'themes' ][ 'detail_shell' ] );
		$this->assertSame( Vulnerabilities::class, $definitions[ 'vulnerabilities' ][ 'render_action_class' ] );
		$this->assertSame( 'File Changes', $definitions[ 'file_locker' ][ 'label' ] );
		$this->assertSame( 'asset_cards', $definitions[ 'file_locker' ][ 'detail_shell' ] );
		$this->assertSame( FileLocker::class, $definitions[ 'file_locker' ][ 'render_action_class' ] );
		$this->assertSame( 'maintenance', $definitions[ 'maintenance' ][ 'detail_shell' ] );
		$this->assertSame( 'category', $definitions[ 'maintenance' ][ 'card_type' ] );
		$this->assertSame( Maintenance::class, $definitions[ 'maintenance' ][ 'render_action_class' ] );
	}

	public function test_drill_hint_patterns_are_group_specific() :void {
		$definitions = ( new ActionsQueueGroupDefinitions() )->all();

		$this->assertSame( 'View %s plugin', $definitions[ 'plugins' ][ 'drill_hint_single' ] );
		$this->assertSame( 'View %s plugins', $definitions[ 'plugins' ][ 'drill_hint_plural' ] );
		$this->assertSame( 'View %s theme', $definitions[ 'themes' ][ 'drill_hint_single' ] );
		$this->assertSame( 'View %s themes', $definitions[ 'themes' ][ 'drill_hint_plural' ] );
		$this->assertSame( 'View %s file', $definitions[ 'wordpress' ][ 'drill_hint_single' ] );
		$this->assertSame( 'View %s files', $definitions[ 'wordpress' ][ 'drill_hint_plural' ] );
		$this->assertSame( 'View %s file', $definitions[ 'malware' ][ 'drill_hint_single' ] );
		$this->assertSame( 'View %s files', $definitions[ 'malware' ][ 'drill_hint_plural' ] );
		$this->assertSame( 'View %s file', $definitions[ 'file_locker' ][ 'drill_hint_single' ] );
		$this->assertSame( 'View %s files', $definitions[ 'file_locker' ][ 'drill_hint_plural' ] );
		$this->assertSame( '', $definitions[ 'vulnerabilities' ][ 'drill_hint_single' ] );
		$this->assertSame( '', $definitions[ 'vulnerabilities' ][ 'drill_hint_plural' ] );
		$this->assertSame( '', $definitions[ 'maintenance' ][ 'drill_hint_single' ] );
		$this->assertSame( '', $definitions[ 'maintenance' ][ 'drill_hint_plural' ] );
	}

	public function test_summary_keys_map_back_to_canonical_group_keys() :void {
		$definitions = new ActionsQueueGroupDefinitions();

		$this->assertSame( 'wordpress', $definitions->groupKeyForSummaryKey( 'wp_files' ) );
		$this->assertSame( 'plugins', $definitions->groupKeyForSummaryKey( 'plugin_files' ) );
		$this->assertSame( 'themes', $definitions->groupKeyForSummaryKey( 'theme_files' ) );
		$this->assertSame( 'vulnerabilities', $definitions->groupKeyForSummaryKey( 'vulnerable_assets' ) );
		$this->assertSame( 'vulnerabilities', $definitions->groupKeyForSummaryKey( 'abandoned' ) );
		$this->assertSame( 'malware', $definitions->groupKeyForSummaryKey( 'malware' ) );
		$this->assertSame( 'file_locker', $definitions->groupKeyForSummaryKey( 'file_locker' ) );
		$this->assertSame( 'maintenance', $definitions->groupKeyForSummaryKey( 'wp_updates' ) );
	}
}
