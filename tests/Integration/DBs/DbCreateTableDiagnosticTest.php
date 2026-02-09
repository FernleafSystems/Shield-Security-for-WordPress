<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Database\DbCon;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Diagnostic test to identify WHY specific DB tables fail to create.
 * Captures the actual SQL and MySQL error for each failing handler.
 */
class DbCreateTableDiagnosticTest extends ShieldIntegrationTestCase {

	/**
	 * For every handler that reports isReady() === false, capture the
	 * CREATE TABLE SQL and the MySQL error, then dump both.
	 */
	public function test_diagnose_table_creation_failures() {
		$con = $this->requireController();

		global $wpdb;
		$diagnostics = [];

		foreach ( \array_keys( DbCon::MAP ) as $dbKey ) {
			try {
				$handler = $con->db_con->load( $dbKey );
			}
			catch ( \Exception $e ) {
				$diagnostics[ $dbKey ] = [
					'status' => 'exception',
					'error'  => $e->getMessage(),
				];
				continue;
			}

			if ( !empty( $handler ) && $handler->isReady() ) {
				$diagnostics[ $dbKey ] = [ 'status' => 'ready' ];
				continue;
			}

			// Handler is NOT ready — try to generate and run the SQL manually
			$schema = $handler->getTableSchema();
			$createSQL = $schema->buildCreate();
			$tableName = $schema->table;

			// Drop the table first (in case a partial creation left debris)
			$wpdb->query( "DROP TABLE IF EXISTS `{$tableName}`" );
			$wpdb->last_error = '';

			// Attempt CREATE TABLE and capture error
			$result = $wpdb->query( $createSQL );
			$mysqlError = $wpdb->last_error;

			$diagnostics[ $dbKey ] = [
				'status'      => 'FAILED',
				'table'       => $tableName,
				'sql'         => $createSQL,
				'query_result' => $result,
				'mysql_error' => $mysqlError,
			];
		}

		// Output diagnostics for all failing handlers
		$failures = \array_filter( $diagnostics, fn( $d ) => $d[ 'status' ] !== 'ready' );

		$output = "\n\n=== DB TABLE CREATION DIAGNOSTICS ===\n";
		$output .= "Total handlers: ".\count( $diagnostics )."\n";
		$output .= "Ready: ".( \count( $diagnostics ) - \count( $failures ) )."\n";
		$output .= "Failed: ".\count( $failures )."\n\n";

		foreach ( $failures as $key => $diag ) {
			$output .= "--- {$key} ---\n";
			$output .= "  Status: {$diag['status']}\n";
			if ( isset( $diag[ 'table' ] ) ) {
				$output .= "  Table: {$diag['table']}\n";
			}
			if ( isset( $diag[ 'mysql_error' ] ) ) {
				$output .= "  MySQL Error: {$diag['mysql_error']}\n";
			}
			if ( isset( $diag[ 'sql' ] ) ) {
				$output .= "  SQL:\n    ".\str_replace( "\n", "\n    ", $diag[ 'sql' ] )."\n";
			}
			if ( isset( $diag[ 'error' ] ) ) {
				$output .= "  Exception: {$diag['error']}\n";
			}
			$output .= "\n";
		}
		$output .= "=== END DIAGNOSTICS ===\n";

		// Use fwrite to stderr to ensure output appears in CI
		\fwrite( STDERR, $output );

		// This test always passes — it's purely diagnostic
		$this->assertNotEmpty( $diagnostics, 'Should have processed all handlers' );
	}

	/**
	 * Directly test that column names with MySQL reserved words are properly handled.
	 */
	public function test_reserved_word_column_detection() {
		// MySQL 8.0 reserved words that might appear as column names
		$mysqlReservedWords = [
			'event', 'condition', 'constraint', 'default', 'describe', 'distinct',
			'float', 'foreign', 'function', 'grant', 'group', 'having', 'if', 'in',
			'index', 'inner', 'insert', 'int', 'integer', 'interval', 'into', 'is',
			'join', 'key', 'keys', 'kill', 'leave', 'left', 'like', 'limit', 'lock',
			'long', 'loop', 'match', 'natural', 'not', 'null', 'on', 'option', 'or',
			'order', 'out', 'outer', 'primary', 'procedure', 'range', 'read',
			'references', 'release', 'rename', 'repeat', 'replace', 'require',
			'return', 'revoke', 'right', 'schema', 'select', 'set', 'show', 'signal',
			'table', 'then', 'to', 'trigger', 'unique', 'unlock', 'update', 'usage',
			'use', 'using', 'values', 'when', 'where', 'while', 'with', 'write',
		];

		$con = $this->requireController();
		$reservedWordColumns = [];

		foreach ( \array_keys( DbCon::MAP ) as $dbKey ) {
			try {
				$handler = $con->db_con->load( $dbKey );
				if ( empty( $handler ) ) {
					continue;
				}
				$schema = $handler->getTableSchema();
				foreach ( $schema->getColumnNames() as $colName ) {
					if ( \in_array( \strtolower( $colName ), $mysqlReservedWords, true ) ) {
						$reservedWordColumns[] = "{$dbKey}.{$colName}";
					}
				}
			}
			catch ( \Exception $e ) {
				// skip
			}
		}

		$msg = empty( $reservedWordColumns )
			? ''
			: 'Columns using MySQL reserved words (need backtick quoting): '.\implode( ', ', $reservedWordColumns );

		\fwrite( STDERR, "\n=== RESERVED WORD CHECK ===\n{$msg}\n=== END ===\n" );

		// Informational only
		$this->assertIsArray( $reservedWordColumns );
	}
}
