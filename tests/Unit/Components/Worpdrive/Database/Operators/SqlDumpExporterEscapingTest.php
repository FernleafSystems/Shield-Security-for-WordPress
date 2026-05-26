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
	use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\{
		Config,
		Exporter,
		SqlDumpValueEscaper,
		Table\TableDataExport
	};
	use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
	use FernleafSystems\Wordpress\Services\Core\Db;
	use FernleafSystems\Wordpress\Services\Services;

	class SqlDumpExporterEscapingTest extends BaseUnitTest {

		private const TABLE = 'wp_worpdrive_dump_escape';

		private $origServiceItems;

		private $origServices;

		protected function setUp() :void {
			parent::setUp();
			Functions\when( 'esc_sql' )->returnArg();
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

		private function assertDumpContainsExpectedRow( string $dump, string $placeholderToken ) :void {
			$this->assertStringContainsString(
				"INSERT INTO `".self::TABLE."` VALUES (7,'50% complete; Bob\\'s 100%% path\\\\test',NULL,42.75,9,0x002562696e,'007%');",
				$dump
			);
			$this->assertStringNotContainsString( $placeholderToken, $dump );
		}

		private function dumpConfig() :Config {
			return ( new Config() )->set( 'hex-blob', true );
		}

		private function installFakeDb() :WorpdriveSqlDumpFakeDb {
			$db = new WorpdriveSqlDumpFakeDb(
				self::TABLE,
				[
					[
						'id'           => 7,
						'message'      => "50% complete; Bob's 100%% path\\test",
						'nullable'     => null,
						'amount'       => '42.75',
						'count_value'  => '9',
						'blob_data'    => "\0%bin",
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
					[ 'Field' => 'numeric_text', 'Type' => 'varchar(20)', 'Key' => '', 'Extra' => '' ],
				]
			);
			$this->getServicesProperty( 'items' )->setValue( null, [
				'service_wpdb' => $db,
			] );
			$this->getServicesProperty( 'services' )->setValue( null, null );
			return $db;
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

		public function __construct( string $table, array $rows, array $columns ) {
			$this->table = $table;
			$this->rows = $rows;
			$this->columns = $columns;
			$this->wpdb = new \wpdb();
		}

		public function selectCustom( $query, $format = \ARRAY_A ) {
			if ( \stripos( $query, 'SHOW FULL COLUMNS FROM' ) === 0 ) {
				return $this->columns;
			}
			if ( \stripos( $query, 'SELECT * FROM `'.$this->table.'`' ) === 0 ) {
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
	}
}
