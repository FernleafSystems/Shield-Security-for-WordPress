<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Common;

use FernleafSystems\Utilities\Data\Adapter\DynProperties;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class TableSchema
 * @package FernleafSystems\Wordpress\Plugin\Shield\Databases\Common
 * @property string   $table
 * @property string   $primary_key
 * @property string[] $cols_ids
 * @property string[] $cols_custom
 * @property string[] $cols_timestamps
 */
class TableSchema {

	const PRIMARY_KEY = 'id';
	use DynProperties;

	public function buildCreate() :string {
		$cols = [];
		foreach ( $this->enumerateColumns() as $col => $def ) {
			$cols[] = sprintf( '%s %s', $col, $def );
		}
		$cols[] = $this->getPrimaryKeyDef();

		return sprintf(
			'CREATE TABLE %s (
				%s
			) %s;',
			$this->table,
			implode( ", ", $cols ),
			Services::WpDb()->getCharCollate()
		);
	}

	/**
	 * @return string[]
	 */
	public function getColumnNames() :array {
		return array_keys( $this->enumerateColumns() );
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

	protected function getPrimaryKeyDef() :string {
		return sprintf( 'PRIMARY KEY  (%s)', $this->getPrimaryKeyColumnName() );
	}

	protected function getTimestampColDef( string $comment = '' ) :string {
		return sprintf( "INT(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT '%s'", str_replace( "'", '', $comment ) );
	}

	protected function getPrimaryKeyColumnName() :string {
		return $this->primary_key ?? static::PRIMARY_KEY;
	}
}
