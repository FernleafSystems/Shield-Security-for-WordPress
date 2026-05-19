<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\Worpdrive\Database;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Data\{
	ChunkedExporter,
	ExportMap,
	PagedExporter
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\{
	Config,
	Exporter
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Exc\TimeLimitReachedException;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldWordPressTestCase;

class ChunkedExporterIntegrationTest extends ShieldWordPressTestCase {

	private array $testTables = [];

	private string $tempDir;

	private string $tableSuffix;

	public function set_up() :void {
		parent::set_up();

		$this->tableSuffix = \strtolower( \bin2hex( \random_bytes( 4 ) ) );
		$this->tempDir = \path_join( \sys_get_temp_dir(), 'shield_worpdrive_export_'.$this->tableSuffix );
		if ( !\mkdir( $this->tempDir, 0755, true ) && !\is_dir( $this->tempDir ) ) {
			$this->fail( 'Failed to create Worpdrive export test directory.' );
		}
	}

	public function tear_down() :void {
		global $wpdb;

		foreach ( $this->testTables as $table ) {
			$wpdb->query( \sprintf( 'DROP TABLE IF EXISTS `%s`', $table ) );
		}
		$this->testTables = [];

		if ( !empty( $this->tempDir ) && \is_dir( $this->tempDir ) ) {
			$files = \glob( \path_join( $this->tempDir, '*' ) );
			if ( \is_array( $files ) ) {
				foreach ( $files as $file ) {
					if ( \is_file( $file ) ) {
						\unlink( $file );
					}
				}
			}
			\rmdir( $this->tempDir );
		}

		parent::tear_down();
	}

	public function testMixedColumnExportRestoresExactRows() :void {
		$table = $this->createMixedTable();
		$expectedRows = $this->fetchRows( $table, 'ORDER BY `id` ASC', [ 'payload' ] );

		$schemaSql = $this->exportSchemaSql( $table );
		$dataFiles = $this->exportDataFiles( $table, 100, 2 );

		$this->assertStringContainsString(
			\sprintf( 'INSERT INTO `%s` (`id`, `title`, `body`, `amount`, `is_active`, `payload`, `created_at`) VALUES', $table ),
			\file_get_contents( $dataFiles[ 0 ] )
		);

		$this->dropAndReplayDump( $table, $schemaSql, $dataFiles );

		$this->assertSame( $expectedRows, $this->fetchRows( $table, 'ORDER BY `id` ASC', [ 'payload' ] ) );
	}

	public function testPrimaryKeyPaginationRoundTripKeepsAllRowsWithGaps() :void {
		$table = $this->createLargePrimaryKeyTable();
		$expectedRows = $this->fetchRows( $table, 'ORDER BY `id` ASC' );

		$schemaSql = $this->exportSchemaSql( $table );
		$dataFiles = $this->exportDataFiles( $table, 500, 100 );

		$this->assertGreaterThan( 2, \count( $dataFiles ) );

		$this->dropAndReplayDump( $table, $schemaSql, $dataFiles );

		$actualRows = $this->fetchRows( $table, 'ORDER BY `id` ASC' );
		$this->assertSame( \count( $expectedRows ), \count( $actualRows ) );
		$this->assertSame( $expectedRows[ 0 ], $actualRows[ 0 ] );
		$this->assertSame( \end( $expectedRows ), \end( $actualRows ) );
		$this->assertSame( $expectedRows, $actualRows );
	}

	public function testCompositePrimaryKeyOffsetPaginationRoundTripRestoresRows() :void {
		$table = $this->createCompositeKeyTable();
		$expectedRows = $this->fetchRows( $table, 'ORDER BY `tenant_id` ASC, `record_id` ASC' );

		$schemaSql = $this->exportSchemaSql( $table );
		$dataFiles = $this->exportDataFiles( $table, 50, 15 );

		$this->assertGreaterThan( 1, \count( $dataFiles ) );

		$this->dropAndReplayDump( $table, $schemaSql, $dataFiles );

		$this->assertSame( $expectedRows, $this->fetchRows( $table, 'ORDER BY `tenant_id` ASC, `record_id` ASC' ) );
	}

	public function testTableWithoutPrimaryKeyOffsetPaginationRoundTripRestoresRows() :void {
		$table = $this->createNoPrimaryKeyTable();
		$expectedRows = $this->fetchRows( $table, 'ORDER BY `seq` ASC' );

		$schemaSql = $this->exportSchemaSql( $table );
		$dataFiles = $this->exportDataFiles( $table, 30, 10 );

		$this->assertGreaterThan( 1, \count( $dataFiles ) );

		$this->dropAndReplayDump( $table, $schemaSql, $dataFiles );

		$this->assertSame( $expectedRows, $this->fetchRows( $table, 'ORDER BY `seq` ASC' ) );
	}

	public function testPagedExporterCanResumePartialExport() :void {
		$table = $this->createResumeTable();
		$expectedRows = $this->fetchRows( $table, 'ORDER BY `id` ASC' );
		$schemaSql = $this->exportSchemaSql( $table );
		$map = new ExportMap( [
			$table => $this->initialMapStatus( 40, 20 ),
		] );

		try {
			( new PagedExporter( $this->tempDir, $map, \time() - 1 ) )->run();
			$this->fail( 'Expected a time-limit exception after the first page.' );
		}
		catch ( TimeLimitReachedException $e ) {
		}

		$partialStatus = $map->status()[ $table ];
		$this->assertSame( 1, $partialStatus[ 'page' ] );
		$this->assertSame( 40, $partialStatus[ 'exported_rows' ] );
		$this->assertSame( 40, $partialStatus[ 'offset' ] );
		$this->assertSame( 0, $partialStatus[ 'completed_at' ] );

		( new PagedExporter( $this->tempDir, $map, \time() + 120 ) )->run();

		$finalStatus = $map->status()[ $table ];
		$this->assertGreaterThan( 0, $finalStatus[ 'completed_at' ] );
		$this->assertSame( \count( $expectedRows ), $finalStatus[ 'exported_rows' ] );

		$this->dropAndReplayDump( $table, $schemaSql, $this->dataFilesForTable( $table ) );

		$this->assertSame( $expectedRows, $this->fetchRows( $table, 'ORDER BY `id` ASC' ) );
	}

	public function testChunkedExporterRejectsMissingTableBeforeSuccessfulDump() :void {
		global $wpdb;

		$dumpFile = \path_join( $this->tempDir, 'missing.sql' );
		$handle = \fopen( $dumpFile, 'w+' );
		if ( !\is_resource( $handle ) ) {
			$this->fail( 'Failed to open temporary dump file.' );
		}

		$previousSuppression = $wpdb->suppress_errors( true );
		try {
			$this->expectException( \Exception::class );
			( new ChunkedExporter( $handle, $this->tableName( 'missing' ), 0, 100, 10 ) )->run();
		}
		finally {
			$wpdb->suppress_errors( $previousSuppression );
			\fclose( $handle );
		}
	}

	private function createMixedTable() :string {
		global $wpdb;

		$table = $this->tableName( 'mixed' );
		$this->createTable( $table, "
			`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`title` VARCHAR(255) NOT NULL,
			`body` TEXT NULL,
			`amount` DECIMAL(10,2) NOT NULL,
			`is_active` TINYINT(1) NOT NULL DEFAULT 0,
			`payload` BLOB NULL,
			`created_at` DATETIME NULL,
			PRIMARY KEY (`id`)
		" );

		$utf8Title = \json_decode( '"Cafe\u00e9 \u2605"', true );
		foreach ( [
			[
				'title'      => "O'Reilly quoted",
				'body'       => "Line one\nLine two with backslash \\ and semicolon ;",
				'amount'     => '12.30',
				'is_active'  => 1,
				'payload'    => "\x00\x01binary",
				'created_at' => '2026-05-19 10:00:00',
			],
			[
				'title'      => $utf8Title,
				'body'       => null,
				'amount'     => '0.00',
				'is_active'  => 0,
				'payload'    => '',
				'created_at' => null,
			],
			[
				'title'      => 'plain row',
				'body'       => 'tab	characters',
				'amount'     => '999.99',
				'is_active'  => 1,
				'payload'    => "\xff\xfe",
				'created_at' => '2026-05-19 11:15:30',
			],
		] as $row ) {
			$this->assertNotFalse( $wpdb->insert( $table, $row ) );
		}

		return $table;
	}

	private function createLargePrimaryKeyTable() :string {
		global $wpdb;

		$table = $this->tableName( 'large_pk' );
		$this->createTable( $table, "
			`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`data` VARCHAR(255) NOT NULL,
			PRIMARY KEY (`id`)
		" );

		$values = [];
		for ( $i = 1; $i <= 1505; $i++ ) {
			$values[] = $wpdb->prepare( '(%s)', 'row_'.$i );
			if ( \count( $values ) >= 100 ) {
				$this->assertNotFalse( $wpdb->query( \sprintf(
					'INSERT INTO `%s` (`data`) VALUES %s',
					$table,
					\implode( ',', $values )
				) ) );
				$values = [];
			}
		}
		if ( !empty( $values ) ) {
			$this->assertNotFalse( $wpdb->query( \sprintf(
				'INSERT INTO `%s` (`data`) VALUES %s',
				$table,
				\implode( ',', $values )
			) ) );
		}

		$this->assertNotFalse( $wpdb->query( \sprintf( 'DELETE FROM `%s` WHERE `id` IN (2,17,1000)', $table ) ) );

		return $table;
	}

	private function createCompositeKeyTable() :string {
		global $wpdb;

		$table = $this->tableName( 'composite' );
		$this->createTable( $table, "
			`tenant_id` VARCHAR(50) NOT NULL,
			`record_id` VARCHAR(50) NOT NULL,
			`data` TEXT NULL,
			PRIMARY KEY (`tenant_id`, `record_id`)
		" );

		for ( $i = 1; $i <= 120; $i++ ) {
			$this->assertNotFalse( $wpdb->insert( $table, [
				'tenant_id' => 'tenant_'.( $i % 7 ),
				'record_id' => \sprintf( 'record_%03d', $i ),
				'data'      => 'composite_'.$i,
			] ) );
		}

		return $table;
	}

	private function createResumeTable() :string {
		global $wpdb;

		$table = $this->tableName( 'resume' );
		$this->createTable( $table, "
			`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`data` VARCHAR(255) NOT NULL,
			PRIMARY KEY (`id`)
		" );

		for ( $i = 1; $i <= 125; $i++ ) {
			$this->assertNotFalse( $wpdb->insert( $table, [ 'data' => 'resume_'.$i ] ) );
		}

		return $table;
	}

	private function createNoPrimaryKeyTable() :string {
		global $wpdb;

		$table = $this->tableName( 'no_pk' );
		$this->createTable( $table, "
			`seq` INT NOT NULL,
			`label` VARCHAR(100) NOT NULL,
			`notes` TEXT NULL
		" );

		for ( $i = 1; $i <= 65; $i++ ) {
			$this->assertNotFalse( $wpdb->insert( $table, [
				'seq'   => $i,
				'label' => \sprintf( 'label_%03d', $i ),
				'notes' => $i % 5 === 0 ? null : 'note_'.$i,
			] ) );
		}

		return $table;
	}

	private function createTable( string $table, string $columnsSql ) :void {
		global $wpdb;

		$this->testTables[] = $table;
		$this->assertNotFalse( $wpdb->query( \sprintf(
			'CREATE TABLE `%s` (%s) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
			$table,
			$columnsSql
		) ) );
	}

	private function exportSchemaSql( string $table ) :string {
		$cfg = ( new Config() )->applyDumpSchemaOptions();
		$cfg->set( 'host', \defined( 'DB_HOST' ) ? DB_HOST : '' );
		$cfg->set( 'database', \defined( 'DB_NAME' ) ? DB_NAME : '' );
		$cfg->set( 'tables', [ $table ] );

		return \implode( "\n", ( new Exporter( $cfg ) )->export()[ 'content' ] );
	}

	private function exportDataFiles( string $table, int $maxPageRows, int $chunkSize ) :array {
		$map = new ExportMap( [
			$table => $this->initialMapStatus( $maxPageRows, $chunkSize ),
		] );

		( new PagedExporter( $this->tempDir, $map, \time() + 120 ) )->run();

		$this->assertGreaterThan( 0, $map->status()[ $table ][ 'completed_at' ] );

		return $this->dataFilesForTable( $table );
	}

	private function initialMapStatus( int $maxPageRows, int $chunkSize ) :array {
		return [
			'offset'        => 0,
			'page'          => 0,
			'completed_at'  => 0,
			'exported_rows' => 0,
			'max_page_rows' => $maxPageRows,
			'chunk_size'    => $chunkSize,
		];
	}

	private function dropAndReplayDump( string $table, string $schemaSql, array $dataFiles ) :void {
		global $wpdb;

		$this->assertNotFalse( $wpdb->query( \sprintf( 'DROP TABLE IF EXISTS `%s`', $table ) ) );
		$this->runSqlScript( $schemaSql );
		foreach ( $dataFiles as $file ) {
			$sql = \file_get_contents( $file );
			if ( $sql === false ) {
				$this->fail( 'Failed to read dump file.' );
			}
			$this->runSqlScript( $sql );
		}
	}

	private function runSqlScript( string $sql ) :void {
		global $wpdb;

		$dbh = $wpdb->dbh;
		if ( $dbh instanceof \mysqli ) {
			while ( $dbh->more_results() ) {
				$dbh->next_result();
				if ( $result = $dbh->store_result() ) {
					$result->free();
				}
			}
			$this->assertTrue( $dbh->multi_query( $sql ), $dbh->error );
			do {
				if ( $result = $dbh->store_result() ) {
					$result->free();
				}
				$this->assertSame( 0, $dbh->errno, $dbh->error );
			} while ( $dbh->more_results() && $dbh->next_result() );
			$this->assertSame( 0, $dbh->errno, $dbh->error );
			return;
		}

		$this->fail( 'Worpdrive round-trip integration tests require mysqli.' );
	}

	private function fetchRows( string $table, string $orderBy, array $binaryColumns = [] ) :array {
		global $wpdb;

		$rows = $wpdb->get_results( \sprintf( 'SELECT * FROM `%s` %s', $table, $orderBy ), ARRAY_A );
		$this->assertIsArray( $rows );

		foreach ( $rows as &$row ) {
			foreach ( $binaryColumns as $column ) {
				$row[ $column ] = $row[ $column ] === null ? null : \bin2hex( (string)$row[ $column ] );
			}
		}
		unset( $row );

		return $rows;
	}

	private function dataFilesForTable( string $table ) :array {
		global $wpdb;

		$unPrefixed = \preg_replace(
			\sprintf( '#^%s#', \preg_quote( $wpdb->prefix, '#' ) ),
			'',
			$table
		);
		$files = \glob( \path_join( $this->tempDir, \sprintf( 'data_%s_*.sql', $unPrefixed ) ) );
		$this->assertIsArray( $files );
		\natsort( $files );

		return \array_values( $files );
	}

	private function tableName( string $slug ) :string {
		global $wpdb;

		return $wpdb->prefix.'shield_wd_'.$slug.'_'.$this->tableSuffix;
	}
}
