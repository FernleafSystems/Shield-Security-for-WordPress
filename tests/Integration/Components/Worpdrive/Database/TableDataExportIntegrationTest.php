<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\Worpdrive\Database;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\{
	Config,
	Table\TableDataExport
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class TableDataExportIntegrationTest extends ShieldIntegrationTestCase {

	private string $table = '';

	public function set_up() {
		parent::set_up();
		global $wpdb;
		$this->table = $wpdb->prefix.'worpdrive_percent_export_test';
		$this->createExportTestTable();
	}

	public function tear_down() {
		if ( !empty( $this->table ) ) {
			$this->dropExportTestTable();
		}
		parent::tear_down();
	}

	public function test_table_data_export_removes_wordpress_placeholder_escapes_from_percent_literals() :void {
		global $wpdb;

		$inserted = $wpdb->insert( $this->table, [
			'id'           => 7,
			'message'      => "50% complete and Bob's note",
			'repeated_pct' => '100%% sure',
			'path_text'    => 'path\\segment 25%',
			'numeric_text' => '007%',
		] );
		$this->assertSame( 1, $inserted, 'Fixture row should be inserted before export.' );

		$exporter = new TableDataExport( $this->table, new Config() );
		$exporter->buildDataRows( [], 'ORDER BY `id` ASC' );

		$dump = \implode( "\n", $exporter->getContent() );

		$this->assertStringContainsString( "50% complete and Bob\\'s note", $dump );
		$this->assertStringContainsString( '100%% sure', $dump );
		$this->assertStringContainsString( 'path\\\\segment 25%', $dump );
		$this->assertStringContainsString( "'007%'", $dump );
		$this->assertStringNotContainsString( $wpdb->placeholder_escape(), $dump );
	}

	private function createExportTestTable() :void {
		$this->runWithoutWordpressTemporaryTableQueryHooks( function () :void {
			global $wpdb;
			$wpdb->query( "DROP TABLE IF EXISTS `{$this->table}`" );
			$wpdb->query(
				"CREATE TABLE `{$this->table}` (
					`id` int(11) unsigned NOT NULL,
					`message` text NOT NULL,
					`repeated_pct` varchar(100) NOT NULL,
					`path_text` varchar(100) NOT NULL,
					`numeric_text` varchar(20) NOT NULL,
					PRIMARY KEY (`id`)
				) ".$wpdb->get_charset_collate()
			);
		} );
		Services::WpDb()->clearResultShowTables();
	}

	private function dropExportTestTable() :void {
		$this->runWithoutWordpressTemporaryTableQueryHooks( function () :void {
			global $wpdb;
			$wpdb->query( "DROP TABLE IF EXISTS `{$this->table}`" );
		} );
		Services::WpDb()->clearResultShowTables();
	}

	private function runWithoutWordpressTemporaryTableQueryHooks( callable $callback ) {
		$createHook = [ $this, '_create_temporary_tables' ];
		$dropHook = [ $this, '_drop_temporary_tables' ];
		$removedCreateHook = \method_exists( $this, '_create_temporary_tables' ) && \has_filter( 'query', $createHook ) !== false;
		$removedDropHook = \method_exists( $this, '_drop_temporary_tables' ) && \has_filter( 'query', $dropHook ) !== false;

		if ( $removedCreateHook ) {
			\remove_filter( 'query', $createHook, 10 );
		}
		if ( $removedDropHook ) {
			\remove_filter( 'query', $dropHook, 10 );
		}

		try {
			return $callback();
		}
		finally {
			if ( $removedCreateHook ) {
				\add_filter( 'query', $createHook, 10 );
			}
			if ( $removedDropHook ) {
				\add_filter( 'query', $dropHook, 10 );
			}
		}
	}
}
