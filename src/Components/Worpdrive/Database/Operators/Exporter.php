<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\Table\TableHelper;
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\Table\TableRowsSqlBuilder;
use FernleafSystems\Wordpress\Services\Services;

class Exporter {

	private Config $cfg;

	private array $content = [];

	private ?int $totalDataRows = null;

	private ?int $previousDataRows = null;

	public function __construct( Config $cfg ) {
		$this->cfg = $cfg;
	}

	public function getContent( bool $flush = false ) :array {
		$content = $this->content;
		if ( $flush ) {
			$this->content = [];
		}
		return $content;
	}

	public function getTotalDataRowsCount() :?int {
		return $this->totalDataRows;
	}

	/**
	 * @throws \Exception
	 */
	public function export() :array {

		$this->buildHeader();

		$this->buildPreDataExport();

		foreach ( $this->cfg->get( 'tables', [] ) as $tableName ) {
			$this->buildForTable( $tableName );
		}

		$this->buildFooter();

		return [
			'content'    => $this->getContent(),
			'rows_count' => $this->totalDataRows,
		];
	}

	/**
	 * TODO: this logic needs entirely restructured
	 * This can be skipped altogether if we're previously exported a schema.sql for use in imports later.
	 * @throws \Exception
	 */
	public function buildForTable( string $table ) :self {
		$tableCreateSQL = ( new TableHelper( $table ) )->showCreate();
		$type = \key( $tableCreateSQL ) === 'Create View' ? 'view' : 'table'; // Create Table

		if ( !$this->cfg->has( 'no-create-info' ) ) {
			( $type === 'table' ) ?
				$this->buildTableStructure( $table, $tableCreateSQL[ 'Create Table' ] )
				: $this->buildViewStructure( $table, $tableCreateSQL[ 'Create View' ] );
		}

		if ( !$this->cfg->has( 'no-data' ) && $type === 'table' ) {
			$this->buildTableDataStructureFull( $table );
		}

		return $this;
	}

	public function buildCreateDatabase() :void {
		$DB = Services::WpDb();
		$DB->getVar( "SHOW VARIABLES LIKE 'character_set_database';" );
		$characterSet = $DB->getVar( "SHOW VARIABLES LIKE 'character_set_database';" );
		$collation = $DB->getVar( "SHOW VARIABLES LIKE 'collation_database';" );
		$this->addContent( [
			"CREATE DATABASE /*!32312 IF NOT EXISTS*/ `".$this->cfg->get( 'database' )."` /*!40100 DEFAULT CHARACTER SET ".$characterSet." COLLATE ".$collation."*/;",
			"USE `".$this->cfg->get( 'database' )."`;",
		] );
	}

	public function buildTableDataStructureStart( string $table ) :self {
		$quotedTable = SqlIdentifier::quote( $table, 'Table name' );
		if ( !$this->cfg->has( 'skip-comments' ) ) {
			$this->addContent( [
				'',
				'--',
				sprintf( '-- Dumping data for table `%s`', $table ),
				'--',
				'',
			] );
		}
		if ( $this->cfg->has( 'add-locks' ) ) {
			$this->addLine( \sprintf( 'LOCK TABLES %s WRITE;', $quotedTable ) );
		}
		if ( $this->cfg->has( 'disable-keys' ) ) {
			$this->addLine( \sprintf( '/*!40000 ALTER TABLE %s DISABLE KEYS */;', $quotedTable ) );
			//"SET FOREIGN_KEY_CHECKS = 0;";
		}
		$this->addLine( '' );
		return $this;
	}

	public function buildTableDataStructureEnd( string $table ) :self {
		$quotedTable = SqlIdentifier::quote( $table, 'Table name' );
		$this->addContent( \array_filter( [
			'',
			$this->cfg->has( 'disable-keys' ) ? \sprintf( '/*!40000 ALTER TABLE %s ENABLE KEYS */;', $quotedTable ) : null,
			$this->cfg->has( 'add-locks' ) ? 'UNLOCK TABLES;' : null,
			'',
		], fn( $line ) => $line !== null ) );
		return $this;
	}

	/**
	 * @throws \Exception
	 */
	public function buildTableDataStructureRows( string $table ) :void {

		// Select all the Rows
		$rows = Services::WpDb()->selectCustom( sprintf(
			'SELECT * FROM %s %s;', SqlIdentifier::quote( $table, 'Table name' ), $this->cfg->has( 'where' ) ? ' WHERE '.$this->cfg->get( 'where' ) : ''
		) );
		if ( !\is_array( $rows ) ) {
			throw new \Exception( \sprintf( 'Database query failed for table: %s', $table ) );
		}

		$this->previousDataRows = \count( $rows );
		$this->totalDataRows = ( $this->totalDataRows ?? 0 ) + $this->previousDataRows;
		if ( empty( $rows ) ) {
			return;
		}

		if ( $this->cfg->has( 'single-transaction' ) ) {
			if ( Services::WpDb()->doSql( "SET GLOBAL TRANSACTION ISOLATION LEVEL REPEATABLE READ; START TRANSACTION;" ) === false ) {
				throw new \Exception( 'Failed to start transaction' );
			}
		}

		$this->addContent( ( new TableRowsSqlBuilder( $this->cfg ) )->buildInsertLines(
			$table,
			$rows,
			( new TableHelper( $table ) )->showColumns()
		) );

		if ( $this->cfg->has( 'single-transaction' ) ) {
			if ( Services::WpDb()->doSql( 'COMMIT;' ) === false ) {
				throw new \Exception( 'Failed to commit transaction' );
			}
		}
	}

	/**
	 * @throws \Exception
	 */
	public function buildTableDataStructureFull( string $table ) :void {
		$this->buildTableDataStructureStart( $table );
		$this->buildTableDataStructureRows( $table );
		$this->buildTableDataStructureEnd( $table );
	}

	public function addDropDatabase() :void {
		$this->addContent( [
			'',
			"/*!40000 DROP DATABASE IF EXISTS `".$this->cfg->get( 'database' )."`*/;"
		] );
	}

	public function buildFooter() :self {
		$this->addContent( [
			'/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;',
			'',
			'/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;',
			'/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;',
			'/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;',
			'/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;',
			'/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;',
			'/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;',
			'/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;',
			''
		] );

		if ( !$this->cfg->has( 'skip-comments' ) ) {
			$this->addLine( sprintf( '-- Dump completed on %s GMT', \gmdate( 'Y-m-d H:i:s' ) ) );
		}

		return $this;
	}

	/**
	 * Returns header for dump file based on a native mysqldump call
	 */
	public function buildPreDataExport() :self {
		if ( !$this->cfg->has( 'no-create-db' ) ) {
			if ( $this->cfg->has( 'add-drop-database' ) ) {
				$this->addDropDatabase();
			}
			$this->buildCreateDatabase();
		}
		return $this;
	}

	/**
	 * Returns header for dump file based on a native mysqldump call
	 */
	public function buildHeader() :self {
		// Some info about software, source and time

		if ( !$this->cfg->has( 'skip-comments' ) ) {
			$this->addContent( [
				'-- WorpDrive SQL Dump',
				'-- ',
				sprintf( '-- Host: %s    Database: %s', $this->cfg->get( 'host' ), $this->cfg->get( 'database' ) ),
				'-- ------------------------------------------------------',
				sprintf( '-- Generation Time: %s GMT', \gmdate( 'r' ) )
			] );
		}

		$this->addContent( [
			'',
			'/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;',
			'/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;',
			'/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;'
		] );

		if ( $this->cfg->has( 'set-charset' ) && $this->cfg->get( 'default-character-set' ) ) {
			$this->addLine( '/*!40101 SET NAMES '.$this->cfg->get( 'default-character-set' ).' */;' );
		}

		$this->addContent( [
			'/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;',
			'/*!40103 SET TIME_ZONE=\'+00:00\' */;',
			'/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;',
			'/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;',
			'/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE=\'NO_AUTO_VALUE_ON_ZERO\' */;',
			'/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;',
			''
		] );

		return $this;
	}

	public function buildTableStructure( string $table, string $createInfo ) :void {
		$quotedTable = SqlIdentifier::quote( $table, 'Table name' );
		if ( !$this->cfg->has( 'skip-comments' ) ) {
			$this->addContent( [
				'',
				'--',
				sprintf( '-- Table structure for table `%s`', $table ),
				'--',
				'',
			] );
		}

		if ( $this->cfg->has( 'add-drop-table' ) ) {
			$this->addLine( \sprintf( 'DROP TABLE IF EXISTS %s;', $quotedTable ) );
		}

		$isWrapSetNames = $this->cfg->has( 'set-charset' ) && $this->cfg->get( 'default-character-set' );
		if ( $isWrapSetNames ) {
			$this->addLine( "/*!40101 SET @saved_cs_client     = @@character_set_client */;" );
			$this->addLine( "/*!40101 SET character_set_client = ".$this->cfg->get( 'default-character-set' )." */;" );
		}

		$this->addLine( $createInfo.';' );

		if ( $isWrapSetNames ) {
			$this->addLine( "/*!40101 SET character_set_client = @saved_cs_client */;" );
		}
	}

	public function buildViewStructure( string $view, string $createInfo ) :void {
		$this->addContent( [
			'',
			'--',
			sprintf( '-- Table structure for view `%s`', $view ),
			$createInfo,
			'--',
			'',
		] );
	}

	public function addLine( string $line ) {
		$this->addContent( [ $line ] );
	}

	public function addContent( array $lines ) {
		$this->content = \array_merge( $this->content, $lines );
	}
}
