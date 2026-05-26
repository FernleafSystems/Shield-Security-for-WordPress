<?php declare( strict_types=1 );

namespace {
	if ( !\defined( 'ARRAY_A' ) ) {
		\define( 'ARRAY_A', 'ARRAY_A' );
	}

	if ( !\class_exists( 'wpdb' ) ) {
		class wpdb {

			public string $placeholderToken = '{wpdb-placeholder-token}';

			public function _real_escape( $data ) :string {
				return \str_replace(
					'%',
					$this->placeholderToken,
					\addcslashes( (string)$data, "\0\n\r\\'\"\x1a" )
				);
			}

			public function remove_placeholder_escape( $query ) :string {
				return \str_replace( $this->placeholderToken, '%', (string)$query );
			}
		}
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\Worpdrive\Database\Operators {

	use Brain\Monkey\Functions;
	use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Data\ChunkedExporter;
	use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\{
		Config,
		Exporter,
		SqlDumpBitValueFormatter,
		SqlDumpIdentifierEscaper,
		SqlDumpValueEscaper,
		TableEnum,
		Table\TableDataExport,
		Table\TableHelper
	};
	use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
	use FernleafSystems\Wordpress\Services\Core\Db;
	use FernleafSystems\Wordpress\Services\Services;

	class SqlDumpExporterEscapingTest extends BaseUnitTest {

		private const TABLE = 'wp_worpdrive_dump_escape';

		private const PERCENT_TABLE = 'wp_worpdrive_%_meta';

		private const SQL_PLACEHOLDER_TOKEN = '{esc-sql-placeholder-token}';

		private $origServiceItems;

		private $origServices;

		protected function setUp() :void {
			parent::setUp();
			Functions\when( 'esc_sql' )->alias(
				fn( $value ) => \str_replace( '%', self::SQL_PLACEHOLDER_TOKEN, (string)$value )
			);
			$this->origServiceItems = $this->getServicesProperty( 'items' )->getValue();
			$this->origServices = $this->getServicesProperty( 'services' )->getValue();
		}

		protected function tearDown() :void {
			$this->getServicesProperty( 'items' )->setValue( null, $this->origServiceItems );
			$this->getServicesProperty( 'services' )->setValue( null, $this->origServices );
			parent::tearDown();
		}

		public function test_table_data_export_preserves_percent_literals_without_losing_sql_escaping() :void {
			$db = $this->installFakeDb();
			$exporter = new TableDataExport( self::TABLE, $this->dumpConfig() );

			$exporter->buildDataRows();

			$this->assertDumpContainsExpectedRow( \implode( "\n", $exporter->getContent() ), $db->placeholderToken() );
		}

		public function test_full_exporter_preserves_percent_literals_without_losing_sql_escaping() :void {
			$db = $this->installFakeDb();
			$exporter = new Exporter( $this->dumpConfig() );

			$exporter->buildTableDataStructureRows( self::TABLE );

			$this->assertDumpContainsExpectedRow( \implode( "\n", $exporter->getContent() ), $db->placeholderToken() );
		}

		public function test_sql_dump_value_escaper_removes_placeholder_hashes_after_real_escape() :void {
			$db = $this->installFakeDb();

			$escaped = ( new SqlDumpValueEscaper() )->escape( "50% and 100%% Bob's path\\test" );

			$this->assertSame( "'50% and 100%% Bob\\'s path\\\\test'", $escaped );
			$this->assertStringNotContainsString( $db->placeholderToken(), $escaped );
		}

		public function test_sql_dump_bit_value_formatter_emits_importable_hex_literals() :void {
			$formatter = new SqlDumpBitValueFormatter();

			$this->assertSame( '0x01', $formatter->format( "\x01", 'bit(1)' ) );
			$this->assertSame( '0xa5', $formatter->format( "\xa5", 'bit(8)' ) );
			$this->assertSame( '0xa5', $formatter->format( '165', 'bit(8)' ) );
			$this->assertSame( '0x31', $formatter->format( '49', 'bit(8)' ) );
			$this->assertSame( '0x01a5', $formatter->format( "\x01\xa5", 'bit(16)' ) );
			$this->assertSame( '0x01a5', $formatter->format( 421, 'bit(16)' ) );
		}

		public function test_table_helper_does_not_apply_wordpress_placeholder_escaping_to_table_identifiers() :void {
			$db = $this->installFakeDb( self::PERCENT_TABLE );

			( new TableHelper( self::PERCENT_TABLE ) )->showColumns();
			( new TableHelper( self::PERCENT_TABLE ) )->showCreate();

			$queries = \implode( "\n", $db->queries() );
			$this->assertStringContainsString( 'SHOW FULL COLUMNS FROM `'.self::PERCENT_TABLE.'`', $queries );
			$this->assertStringContainsString( 'SHOW CREATE TABLE `'.self::PERCENT_TABLE.'`', $queries );
			$this->assertStringNotContainsString( self::SQL_PLACEHOLDER_TOKEN, $queries );
		}

		/**
		 * @dataProvider identifierEscapingProvider
		 */
		public function test_sql_dump_identifier_escaper_quotes_mysql_identifiers( string $identifier, string $expected ) :void {
			$this->assertSame( $expected, ( new SqlDumpIdentifierEscaper() )->escape( $identifier ) );
		}

		public function identifierEscapingProvider() :array {
			return [
				'normal'         => [ 'wp_posts', '`wp_posts`' ],
				'percent'        => [ 'wp_50%_table', '`wp_50%_table`' ],
				'reserved word'  => [ 'select', '`select`' ],
				'spaces'         => [ 'order details', '`order details`' ],
				'embedded ticks' => [ 'name`with`tick', '`name``with``tick`' ],
			];
		}

		public function test_table_enum_count_query_escapes_percent_table_identifiers_without_placeholder_tokens() :void {
			$db = new WorpdriveTableEnumFakeDb( 'wp_', self::PERCENT_TABLE );
			$this->installDbService( $db );

			$tables = ( new TableEnum() )->enum();

			$this->assertSame( 12, $tables[ self::PERCENT_TABLE ][ 'rows' ] );
			$this->assertSame(
				'SELECT COUNT(*) AS `total_records` FROM `'.self::PERCENT_TABLE.'`',
				$db->countQueries()[ 0 ]
			);
			$this->assertStringNotContainsString( self::SQL_PLACEHOLDER_TOKEN, $db->countQueries()[ 0 ] );
		}

		public function test_table_data_export_complete_insert_escapes_column_identifiers_without_changing_values() :void {
			$db = $this->installCompleteInsertFakeDb();
			$exporter = new TableDataExport( self::PERCENT_TABLE, $this->completeInsertConfig() );

			$exporter->buildDataRows();

			$dump = \implode( "\n", $exporter->getContent() );
			$this->assertStringContainsString(
				"INSERT INTO `".self::PERCENT_TABLE."` (`select`, `percent%col`, `tick``col`) VALUES (5,'50% complete','Bob\\'s path\\\\file');",
				$dump
			);
			$this->assertStringNotContainsString( $db->placeholderToken(), $dump );
			$this->assertStringNotContainsString( self::SQL_PLACEHOLDER_TOKEN, $dump );
		}

		public function test_full_exporter_complete_insert_escapes_column_identifiers_without_changing_values() :void {
			$db = $this->installCompleteInsertFakeDb();
			$exporter = new Exporter( $this->completeInsertConfig() );

			$exporter->buildTableDataStructureRows( self::PERCENT_TABLE );

			$dump = \implode( "\n", $exporter->getContent() );
			$this->assertStringContainsString(
				"INSERT INTO `".self::PERCENT_TABLE."` (`select`, `percent%col`, `tick``col`) VALUES (5,'50% complete','Bob\\'s path\\\\file');",
				$dump
			);
			$this->assertStringNotContainsString( $db->placeholderToken(), $dump );
			$this->assertStringNotContainsString( self::SQL_PLACEHOLDER_TOKEN, $dump );
		}

		public function test_chunked_exporter_uses_safe_identifiers_and_value_conversion_in_active_export_path() :void {
			$table = 'wp_worpdrive_%_chunk';
			$db = $this->installFakeDb( $table );
			$dumpFile = \fopen( 'php://temp', 'w+' );
			$this->assertIsResource( $dumpFile );

			$status = ( new ChunkedExporter( $dumpFile, $table, 0, 100, 10 ) )->run();

			\rewind( $dumpFile );
			$dump = (string)\stream_get_contents( $dumpFile );
			\fclose( $dumpFile );

			$this->assertTrue( $status[ 'table_export_complete' ] );
			$this->assertSame( 7, $status[ 'current_offset' ] );
			$this->assertSame( 1, $status[ 'exported_rows' ] );
			$this->assertStringContainsString( 'LOCK TABLES `'.$table.'` WRITE;', $dump );
			$this->assertStringContainsString(
				"INSERT INTO `".$table."` VALUES (7,'50% complete; Bob\\'s 100%% path\\\\test',NULL,42.75,9,0x002562696e,'',0x01,0xa5,0x31,NULL,0x01a5,'007%');",
				$dump
			);
			$queries = \implode( "\n", $db->queries() );
			$this->assertStringContainsString( 'SHOW FULL COLUMNS FROM `'.$table.'`', $queries );
			$this->assertStringContainsString( 'SELECT * FROM `'.$table.'`  WHERE `id` >= 0 ORDER BY `id` ASC  LIMIT 10 OFFSET 0;', $queries );
			$this->assertStringContainsString( 'SELECT * FROM `'.$table.'`  WHERE `id` > 7 ORDER BY `id` ASC  LIMIT 10 OFFSET 0;', $queries );
			$this->assertStringNotContainsString( $db->placeholderToken(), $dump );
			$this->assertStringNotContainsString( self::SQL_PLACEHOLDER_TOKEN, $queries );
		}

		private function assertDumpContainsExpectedRow( string $dump, string $placeholderToken ) :void {
			$this->assertStringContainsString(
				"INSERT INTO `".self::TABLE."` VALUES (7,'50% complete; Bob\\'s 100%% path\\\\test',NULL,42.75,9,0x002562696e,'',0x01,0xa5,0x31,NULL,0x01a5,'007%');",
				$dump
			);
			$this->assertStringNotContainsString( $placeholderToken, $dump );
		}

		private function dumpConfig() :Config {
			return ( new Config() )->set( 'hex-blob', true );
		}

		private function completeInsertConfig() :Config {
			return $this->dumpConfig()->set( 'complete-insert', true );
		}

		private function installFakeDb( string $table = self::TABLE ) :WorpdriveSqlDumpFakeDb {
			$db = new WorpdriveSqlDumpFakeDb(
				$table,
				[
					[
						'id'           => 7,
						'message'      => "50% complete; Bob's 100%% path\\test",
						'nullable'     => null,
						'amount'       => '42.75',
						'count_value'  => '9',
						'blob_data'    => "\0%bin",
						'empty_blob'   => '',
						'flag_bit'     => "\x01",
						'wide_bit'     => "\xa5",
						'digit_bit'    => '49',
						'nullable_bit' => null,
						'wide16_bit'   => "\x01\xa5",
						'numeric_text' => '007%',
					],
				],
				[
					[ 'Field' => 'id', 'Type' => 'int(11) unsigned', 'Key' => 'PRI', 'Extra' => 'auto_increment' ],
					[ 'Field' => 'message', 'Type' => 'text', 'Key' => '', 'Extra' => '' ],
					[ 'Field' => 'nullable', 'Type' => 'varchar(20)', 'Key' => '', 'Extra' => '' ],
					[ 'Field' => 'amount', 'Type' => 'decimal(10,2)', 'Key' => '', 'Extra' => '' ],
					[ 'Field' => 'count_value', 'Type' => 'int(11)', 'Key' => '', 'Extra' => '' ],
					[ 'Field' => 'blob_data', 'Type' => 'blob', 'Key' => '', 'Extra' => '' ],
					[ 'Field' => 'empty_blob', 'Type' => 'blob', 'Key' => '', 'Extra' => '' ],
					[ 'Field' => 'flag_bit', 'Type' => 'bit(1)', 'Key' => '', 'Extra' => '' ],
					[ 'Field' => 'wide_bit', 'Type' => 'bit(8)', 'Key' => '', 'Extra' => '' ],
					[ 'Field' => 'digit_bit', 'Type' => 'bit(8)', 'Key' => '', 'Extra' => '' ],
					[ 'Field' => 'nullable_bit', 'Type' => 'bit(1)', 'Key' => '', 'Extra' => '' ],
					[ 'Field' => 'wide16_bit', 'Type' => 'bit(16)', 'Key' => '', 'Extra' => '' ],
					[ 'Field' => 'numeric_text', 'Type' => 'varchar(20)', 'Key' => '', 'Extra' => '' ],
				]
			);
			$this->installDbService( $db );
			return $db;
		}

		private function installCompleteInsertFakeDb() :WorpdriveSqlDumpFakeDb {
			$db = new WorpdriveSqlDumpFakeDb(
				self::PERCENT_TABLE,
				[
					[
						'select'      => 5,
						'percent%col' => '50% complete',
						'tick`col'    => "Bob's path\\file",
					],
				],
				[
					[ 'Field' => 'select', 'Type' => 'int(11)', 'Key' => 'PRI', 'Extra' => 'auto_increment' ],
					[ 'Field' => 'percent%col', 'Type' => 'varchar(20)', 'Key' => '', 'Extra' => '' ],
					[ 'Field' => 'tick`col', 'Type' => 'varchar(20)', 'Key' => '', 'Extra' => '' ],
				]
			);
			$this->installDbService( $db );
			return $db;
		}

		private function installDbService( Db $db ) :void {
			$this->getServicesProperty( 'items' )->setValue( null, [
				'service_wpdb' => $db,
			] );
			$this->getServicesProperty( 'services' )->setValue( null, null );
		}

		private function getServicesProperty( string $propertyName ) :\ReflectionProperty {
			$reflection = new \ReflectionClass( Services::class );
			$property = $reflection->getProperty( $propertyName );
			$property->setAccessible( true );
			return $property;
		}
	}

	class WorpdriveSqlDumpFakeDb extends Db {

		private string $table;

		private array $rows;

		private array $columns;

		private array $queries = [];

		public function __construct( string $table, array $rows, array $columns ) {
			$this->table = $table;
			$this->rows = $rows;
			$this->columns = $columns;
			$this->wpdb = new \wpdb();
		}

		public function selectCustom( $query, $format = \ARRAY_A ) {
			$this->queries[] = $query;
			if ( \stripos( $query, 'SHOW FULL COLUMNS FROM' ) === 0 ) {
				return $this->columns;
			}
			if ( \stripos( $query, 'SHOW CREATE TABLE' ) === 0 ) {
				return [
					[
						'Create Table' => 'CREATE TABLE `'.$this->table.'` (`id` int(11) unsigned NOT NULL)',
					],
				];
			}
			if ( \stripos( $query, 'SELECT * FROM `'.$this->table.'`' ) === 0 ) {
				if ( \str_contains( $query, ' WHERE `id` > ' ) ) {
					return [];
				}
				return $this->rows;
			}
			throw new \RuntimeException( 'Unexpected query: '.$query );
		}

		public function loadWpdb() :\wpdb {
			return $this->wpdb;
		}

		public function placeholderToken() :string {
			return $this->wpdb->placeholderToken;
		}

		public function getPrefix( bool $siteBase = true ) :string {
			return 'wp_';
		}

		public function queries() :array {
			return $this->queries;
		}
	}

	class WorpdriveTableEnumFakeDb extends Db {

		private string $prefix;

		private string $table;

		private array $countQueries = [];

		public function __construct( string $prefix, string $table ) {
			$this->prefix = $prefix;
			$this->table = $table;
		}

		public function showTableStatus( $format = \ARRAY_A ) :array {
			return [
				[
					'Name'           => $this->table,
					'Rows'           => 0,
					'Avg_row_length' => 4,
					'Data_length'    => 1024,
					'Index_length'   => 512,
					'Engine'         => 'InnoDB',
				],
			];
		}

		public function getVar( $query ) {
			$this->countQueries[] = $query;
			return 12;
		}

		public function getPrefix( bool $siteBase = true ) :string {
			return $this->prefix;
		}

		public function countQueries() :array {
			return $this->countQueries;
		}
	}
}
