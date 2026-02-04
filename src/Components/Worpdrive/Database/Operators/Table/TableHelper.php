<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\Table;

use FernleafSystems\Wordpress\Services\Services;

class TableHelper {

	private string $table;

	private ?array $columns = null;

	public function __construct( string $table ) {
		$this->table = $table;
	}

	/**
	 * @throws \Exception
	 */
	public function getAppropriatePrimaryKeyForOrdering() :?string {
		$priKey = $this->getStandardPrimaryKeyForTable();
		$theKey = ( !empty( $priKey ) && $this->hasColumn( $priKey ) ) ? $priKey : null;
		if ( empty( $theKey ) ) {
			$theKey = $this->detectPossiblePrimaryKey();
		}
		return $theKey;
	}

	/**
	 * @throws \Exception
	 */
	public function showColumns() :array {
		if ( $this->columns === null ) {
			$colResults = Services::WpDb()->selectCustom( sprintf( "SHOW FULL COLUMNS FROM `%s`", esc_sql( $this->table ) ) );
			if ( !\is_array( $colResults ) ) {
				throw new \Exception( 'No columns in results' );
			}
			$this->columns = [];
			foreach ( $colResults as $colResult ) {
				$this->columns[ $colResult[ 'Field' ] ] = $colResult;
			}
		}
		return $this->columns;
	}

	/**
	 * @throws \Exception
	 */
	public function hasColumn( string $column ) :bool {
		return isset( $this->showColumns()[ $column ] );
	}

	/**
	 * @throws \Exception
	 */
	public function detectPossiblePrimaryKey() :?string {
		$primaryKeyColumn = null;
		$columns = $this->showColumns();
		foreach ( $columns as $column => $c ) {
			if ( !empty( $c[ 'Key' ] ) && \strtolower( $c[ 'Key' ] ) === 'pri'
				 && !empty( $c[ 'Extra' ] ) && \strtolower( $c[ 'Extra' ] ) === 'auto_increment'
				 && !empty( $c[ 'Type' ] ) && \str_contains( \strtolower( $c[ 'Type' ] ), 'int' )
				 && \str_contains( \strtolower( $c[ 'Type' ] ), 'unsigned' )
			) {
				$primaryKeyColumn = $column;
				break;
			}
		}
		return $primaryKeyColumn;
	}

	/**
	 * @return array - length 1; key is "Create Table" or "Create View"; value is SQL table create statement.
	 * @throws \Exception
	 */
	public function showCreate() :array {
		$tableCreateSQL = Services::WpDb()
								  ->selectCustom( sprintf( 'SHOW CREATE TABLE `%s`', esc_sql( $this->table ) ) );
		if ( !\is_array( $tableCreateSQL ) || \count( $tableCreateSQL ) !== 1 ) {
			throw new \Exception( sprintf( 'show create table failed for %s', $this->table ) );
		}
		return \current( $tableCreateSQL );
	}

	private function getStandardPrimaryKeyForTable() :?string {
		$unPrefixed = \preg_replace( sprintf( '#^%s#i', Services::WpDb()->getPrefix() ), '', $this->table, 1 );
		if ( \str_starts_with( $unPrefixed, 'icwp_wpsf_' ) ) {
			$key = 'id';
		}
		else {
			$key = ( new EnumTablePrimaryKeys() )->all()[ $unPrefixed ] ?? null;
			if ( empty( $key ) && \function_exists( 'is_multisite' ) && is_multisite()
				 && \preg_match( '#^\d+_(.+)#', $unPrefixed, $matches ) ) {
				$key = ( new EnumTablePrimaryKeys() )->wordpressStd()[ $matches[ 1 ] ] ?? null;
			}
		}
		return $key;
	}
}