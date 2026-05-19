<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\Worpdrive\Database;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Data\{
	ChunkedExporter,
	ExportMap
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\{
	Config,
	SqlIdentifier
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\Table\TableRowsSqlBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use Mockery;

class ChunkedExporterTest extends BaseUnitTest {

	private $tempFileHandle;

	protected function setUp() :void {
		parent::setUp();
		$this->tempFileHandle = \tmpfile();
	}

	protected function tearDown() :void {
		if ( \is_resource( $this->tempFileHandle ) ) {
			\fclose( $this->tempFileHandle );
		}
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * @dataProvider invalidExportMapProvider
	 */
	public function testExportMapRejectsInvalidStatus( array $status ) :void {
		$this->expectException( \InvalidArgumentException::class );

		new ExportMap( [
			'wp_allowed_table' => $status,
		], [ 'wp_allowed_table' ] );
	}

	public function invalidExportMapProvider() :array {
		$valid = $this->validExportMapStatus();

		return [
			'missing offset'      => [ \array_diff_key( $valid, [ 'offset' => true ] ) ],
			'negative offset'    => [ \array_merge( $valid, [ 'offset' => -1 ] ) ],
			'negative page'      => [ \array_merge( $valid, [ 'page' => -1 ] ) ],
			'negative exported'  => [ \array_merge( $valid, [ 'exported_rows' => -1 ] ) ],
			'boolean chunk size' => [ \array_merge( $valid, [ 'chunk_size' => true ] ) ],
			'zero chunk size'    => [ \array_merge( $valid, [ 'chunk_size' => 0 ] ) ],
			'zero max rows'      => [ \array_merge( $valid, [ 'max_page_rows' => 0 ] ) ],
			'string page text'   => [ \array_merge( $valid, [ 'page' => 'one' ] ) ],
		];
	}

	public function testExportMapNormalisesNumericStringsAndDefaultMaxPageRows() :void {
		$map = new ExportMap( [
			'wp_allowed_table' => [
				'offset'        => '12',
				'page'          => '2',
				'completed_at'  => '0',
				'exported_rows' => '34',
				'chunk_size'    => '25',
			],
		], [ 'wp_allowed_table' ] );

		$this->assertSame( [
			'wp_allowed_table' => [
				'offset'        => 12,
				'page'          => 2,
				'completed_at'  => 0,
				'exported_rows' => 34,
				'max_page_rows' => 1000,
				'chunk_size'    => 25,
			],
		], $map->status() );
	}

	public function testExportMapRejectsUnknownAllowedTable() :void {
		$this->expectException( \InvalidArgumentException::class );

		new ExportMap( [
			'wp_missing_table' => $this->validExportMapStatus(),
		], [ 'wp_allowed_table' ] );
	}

	public function testExportMapRejectsUnsafeTableName() :void {
		$this->expectException( \InvalidArgumentException::class );

		new ExportMap( [
			'wp_allowed_table` WHERE 1=1 --' => $this->validExportMapStatus(),
		] );
	}

	public function testSqlIdentifierQuotesSafeNames() :void {
		$this->assertSame( '`wp_table_123`', SqlIdentifier::quote( 'wp_table_123' ) );
		$this->assertSame( 'wp_table_123', SqlIdentifier::assertAllowedTable( 'wp_table_123', [ 'wp_table_123' ] ) );
	}

	/**
	 * @dataProvider unsafeIdentifierProvider
	 */
	public function testSqlIdentifierRejectsUnsafeNames( string $identifier ) :void {
		$this->expectException( \InvalidArgumentException::class );

		SqlIdentifier::quote( $identifier );
	}

	public function unsafeIdentifierProvider() :array {
		return [
			'empty'      => [ '' ],
			'backtick'   => [ 'wp`table' ],
			'dot'        => [ 'wp.table' ],
			'whitespace' => [ 'wp table' ],
			'nul'        => [ "wp\0table" ],
		];
	}

	/**
	 * @dataProvider invalidChunkedExporterConstructorProvider
	 */
	public function testChunkedExporterRejectsInvalidConstructorInputs( string $table, int $offset, int $maxRows, int $chunkSize ) :void {
		$this->expectException( \Exception::class );

		new ChunkedExporter( $this->tempFileHandle, $table, $offset, $maxRows, $chunkSize );
	}

	public function invalidChunkedExporterConstructorProvider() :array {
		return [
			'unsafe table'    => [ 'wp_table`', 0, 100, 10 ],
			'negative offset' => [ 'wp_table', -1, 100, 10 ],
			'zero max rows'   => [ 'wp_table', 0, 0, 10 ],
			'zero chunk size' => [ 'wp_table', 0, 100, 0 ],
		];
	}

	public function testDumpDataOptionsUseCompleteInserts() :void {
		$this->assertTrue( ( new Config() )->applyDumpDataOptions()->has( 'complete-insert' ) );
	}

	public function testTableRowsSqlBuilderSerialisesNullNumericAndBinaryValues() :void {
		$cfg = ( new Config() )
			->set( 'hex-blob', true )
			->set( 'complete-insert', true );

		$lines = ( new TableRowsSqlBuilder( $cfg ) )->buildInsertLines(
			'wp_export_table',
			[
				[
					'id'      => 5,
					'amount'  => '12.30',
					'payload' => "\x01\xff",
					'note'    => null,
				],
			],
			[
				'id'      => [ 'Type' => 'bigint(20) unsigned' ],
				'amount'  => [ 'Type' => 'decimal(10,2)' ],
				'payload' => [ 'Type' => 'blob' ],
				'note'    => [ 'Type' => 'varchar(255)' ],
			]
		);

		$this->assertSame(
			'INSERT INTO `wp_export_table` (`id`, `amount`, `payload`, `note`) VALUES (5,12.30,0x01ff,NULL);',
			$lines[ 0 ]
		);
	}

	public function testTableRowsSqlBuilderRejectsMissingColumnMetadata() :void {
		$this->expectException( \Exception::class );

		( new TableRowsSqlBuilder( new Config() ) )->buildInsertLines(
			'wp_export_table',
			[ [ 'id' => 1 ] ],
			[]
		);
	}

	public function testTableRowsSqlBuilderRejectsUnsafeColumnNames() :void {
		$this->expectException( \InvalidArgumentException::class );

		( new TableRowsSqlBuilder( new Config() ) )->buildInsertLines(
			'wp_export_table',
			[ [ 'bad`column' => 'value' ] ],
			[ 'bad`column' => [ 'Type' => 'varchar(255)' ] ]
		);
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testTableRowsSqlBuilderEscapesTextValues() :void {
		$services = Mockery::mock( 'alias:FernleafSystems\Wordpress\Services\Services' );
		$db = Mockery::mock();
		$db->shouldReceive( 'loadWpdb' )->andReturnSelf();
		$db->shouldReceive( '_real_escape' )->with( "O'Reilly\nLine" )->andReturn( "O\\'Reilly\\nLine" );
		$services->shouldReceive( 'WpDb' )->andReturn( $db );

		$lines = ( new TableRowsSqlBuilder( ( new Config() )->set( 'complete-insert', true ) ) )->buildInsertLines(
			'wp_export_table',
			[
				[
					'id'   => 7,
					'note' => "O'Reilly\nLine",
				],
			],
			[
				'id'   => [ 'Type' => 'bigint(20) unsigned' ],
				'note' => [ 'Type' => 'text' ],
			]
		);

		$this->assertSame(
			"INSERT INTO `wp_export_table` (`id`, `note`) VALUES (7,'O\\'Reilly\\nLine');",
			$lines[ 0 ]
		);
	}

	private function validExportMapStatus() :array {
		return [
			'offset'        => 0,
			'page'          => 0,
			'completed_at'  => 0,
			'exported_rows' => 0,
			'max_page_rows' => 100,
			'chunk_size'    => 10,
		];
	}
}
