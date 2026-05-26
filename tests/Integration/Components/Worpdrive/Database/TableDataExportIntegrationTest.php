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
		$this->table = $wpdb->prefix.'worpdrive_percent_%_export_test';
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

		$inserted = $wpdb->query(
			"INSERT INTO {$this->tableIdentifier()} ".
			"(`id`, `message`, `repeated_pct`, `path_text`, `numeric_text`, `amount`, `flag_bit`, `wide_bit`, `digit_bit`, `blob_data`) ".
			"VALUES (7, {$this->sqlString( "50% complete and Bob's note" )}, {$this->sqlString( '100%% sure' )}, ".
			"{$this->sqlString( 'path\\segment 25%' )}, {$this->sqlString( '007%' )}, 42.75, b'1', b'10100101', b'00110001', UNHEX('002562696e'))"
		);
		$this->assertSame( 1, $inserted, 'Fixture row should be inserted before export.' );

		$exporter = new TableDataExport( $this->table, new Config() );
		$exporter->buildDataRows( [], 'ORDER BY `id` ASC' );

		$dump = \implode( "\n", $exporter->getContent() );

		$this->assertStringContainsString( "50% complete and Bob\\'s note", $dump );
		$this->assertStringContainsString( '100%% sure', $dump );
		$this->assertStringContainsString( 'path\\\\segment 25%', $dump );
		$this->assertStringContainsString( "'007%'", $dump );
		$this->assertStringContainsString( '0x01', $dump );
		$this->assertStringContainsString( '0xa5', $dump );
		$this->assertStringContainsString( '0x31', $dump );
		$this->assertStringNotContainsString( $wpdb->placeholder_escape(), $dump );

		$this->assertSame( 1, $wpdb->query( "DELETE FROM {$this->tableIdentifier()} WHERE `id`=7" ) );
		foreach ( \array_filter( \explode( "\n", $dump ), fn( string $line ) => \str_starts_with( $line, 'INSERT INTO ' ) ) as $insertLine ) {
			$this->assertSame( 1, $wpdb->query( $insertLine ), 'Generated INSERT SQL should replay cleanly.' );
		}

		$restored = $wpdb->get_row(
			"SELECT `message`, `repeated_pct`, `path_text`, `numeric_text`, `amount`, ".
			"ORD(`flag_bit`) AS `flag_bit_ord`, ORD(`wide_bit`) AS `wide_bit_ord`, ORD(`digit_bit`) AS `digit_bit_ord`, HEX(`blob_data`) AS `blob_hex` ".
			"FROM {$this->tableIdentifier()} WHERE `id`=7",
			ARRAY_A
		);

		$this->assertSame( "50% complete and Bob's note", $restored[ 'message' ] );
		$this->assertSame( '100%% sure', $restored[ 'repeated_pct' ] );
		$this->assertSame( 'path\\segment 25%', $restored[ 'path_text' ] );
		$this->assertSame( '007%', $restored[ 'numeric_text' ] );
		$this->assertSame( '42.75', $restored[ 'amount' ] );
		$this->assertSame( '1', (string)$restored[ 'flag_bit_ord' ] );
		$this->assertSame( '165', (string)$restored[ 'wide_bit_ord' ] );
		$this->assertSame( '49', (string)$restored[ 'digit_bit_ord' ] );
		$this->assertSame( '002562696E', $restored[ 'blob_hex' ] );
	}

	private function createExportTestTable() :void {
		$this->runWithoutWordpressTemporaryTableQueryHooks( function () :void {
			global $wpdb;
			$wpdb->query( "DROP TABLE IF EXISTS {$this->tableIdentifier()}" );
			$wpdb->query(
				"CREATE TABLE {$this->tableIdentifier()} (
					`id` int(11) unsigned NOT NULL,
					`message` text NOT NULL,
					`repeated_pct` varchar(100) NOT NULL,
					`path_text` varchar(100) NOT NULL,
					`numeric_text` varchar(20) NOT NULL,
					`amount` decimal(10,2) NOT NULL,
					`flag_bit` bit(1) NOT NULL,
					`wide_bit` bit(8) NOT NULL,
					`digit_bit` bit(8) NOT NULL,
					`blob_data` blob NOT NULL,
					PRIMARY KEY (`id`)
				) ".$wpdb->get_charset_collate()
			);
		} );
		Services::WpDb()->clearResultShowTables();
	}

	private function dropExportTestTable() :void {
		$this->runWithoutWordpressTemporaryTableQueryHooks( function () :void {
			global $wpdb;
			$wpdb->query( "DROP TABLE IF EXISTS {$this->tableIdentifier()}" );
		} );
		Services::WpDb()->clearResultShowTables();
	}

	private function tableIdentifier() :string {
		return '`'.\str_replace( '`', '``', $this->table ).'`';
	}

	private function sqlString( string $value ) :string {
		global $wpdb;
		return "'".$wpdb->remove_placeholder_escape( $wpdb->_real_escape( $value ) )."'";
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
