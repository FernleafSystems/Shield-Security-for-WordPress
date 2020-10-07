<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class TableBuilder
 * @package FernleafSystems\Wordpress\Plugin\Shield\Databases\Base
 * @property string   $table
 * @property string   $primary_key
 * @property string[] $cols_ids
 * @property string[] $cols_custom
 * @property string[] $cols_timestamps
 */
class TableBuilder {

	const PRIMARY_KEY = 'id';
	use StdClassAdapter;

	public function create() {
		$DB = Services::WpDb();
		$sql = $this->buildSqlCreate();
		$DB->getIfTableExists( $this->table ) ? $DB->dbDelta( $sql ) : $DB->doSql( $sql );
	}

	public function buildSqlCreate() :string {
		$cols = [];
		foreach ( $this->enumerateColumns() as $col => $def ) {
			$cols[] = sprintf( '%s %s', $col, $def );
		}
		$cols[] = $this->getPrimaryKeySpec();

		$sqlCreate = "CREATE TABLE {{TABLE_NAME}} (
			".implode( ", ", $cols )."
		) {{TABLE_COLLATE}};";

		return str_replace( '{{TABLE_NAME}}', $this->table,
			str_replace( '{{TABLE_COLLATE}}', Services::WpDb()->getCharCollate(), $sqlCreate ) );
	}

	/**
	 * @return string[]
	 */
	public function enumerateColumns() :array {
		return array_merge(
			$this->getColumn_ID(),
			$this->cols_custom ?? [],
			$this->getColumnns_Timestamps()
		);
	}

	/**
	 * @return string[]
	 */
	protected function getColumn_ID() :array {
		return [
			$this->getPrimaryKeyColumnName() => 'INT(11) UNSIGNED NOT NULL AUTO_INCREMENT',
		];
	}

	/**
	 * @return string[]
	 */
	protected function getColumnns_Timestamps() :array {
		return array_map(
			function ( $comment ) {
				return $this->getTimestampColDef( $comment );
			},
			array_merge(
				$this->cols_timestamps ?? [],
				[
					'created_at' => 'Created At',
					'deleted_at' => 'Soft Deleted At',
				]
			)
		);
	}

	protected function getPrimaryKeySpec() :string {
		return sprintf( 'PRIMARY KEY  (%s)', $this->getPrimaryKeyColumnName() );
	}

	protected function getTimestampColDef( string $comment = '' ) :string {
		return sprintf( "INT(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT '%s'", str_replace( "'", '', $comment ) );
	}

	protected function getPrimaryKeyColumnName() :string {
		return $this->primary_key ?? static::PRIMARY_KEY;
	}
}
