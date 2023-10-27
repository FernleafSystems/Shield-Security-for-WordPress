<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Common;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string   $slug
 * @property string   $table
 * @property string   $primary_key
 * @property string[] $cols_ids
 * @property string[] $cols_custom
 * @property string[] $cols_timestamps
 * @property string   $col_older_than
 * @property bool     $has_updated_at
 * @property int      $autoexpire
 * @property bool     $has_ip_col
 * @property bool     $is_ip_binary
 */
class TableSchema extends DynPropertiesClass {

	public const PRIMARY_KEY = 'id';

	public function __get( string $key ) {
		switch ( $key ) {
			case 'has_ip_col':
				$val = \array_key_exists( 'ip', $this->enumerateColumns() );
				break;
			case 'is_ip_binary':
				$val = $this->has_ip_col && ( stripos( $this->cols_custom[ 'ip' ], 'varbinary' ) !== false );
				break;
			default:
				$val = parent::__get( $key );
				break;
		}
		return $val;
	}

	public function buildCreate() :string {
		$cols = [];
		foreach ( $this->enumerateColumns() as $col => $def ) {
			$cols[] = sprintf( '%s %s', $col, $def );
		}
		$cols[] = $this->getPrimaryKeyDef();

		return sprintf(
			'CREATE TABLE %s ( %s ) %s;',
			$this->table,
			\implode( ", ", $cols ),
			Services::WpDb()->getCharCollate()
		);
	}

	/**
	 * @return string[]
	 */
	public function getColumnNames() :array {
		return \array_keys( $this->enumerateColumns() );
	}

	/**
	 * @return string[]
	 */
	public function enumerateColumns() :array {
		return \array_merge(
			$this->getColumn_ID(),
			$this->cols_custom ?? [],
			$this->getColumns_Timestamps()
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
	protected function getColumns_Timestamps() :array {

		$standardTsCols = [
			'created_at' => 'Created At',
			'deleted_at' => 'Soft Deleted At',
		];

		if ( $this->has_updated_at && !\array_key_exists( 'updated_at', $this->cols_timestamps ) ) {
			$standardTsCols = \array_merge(
				[ 'updated_at' => 'Updated At', ],
				$standardTsCols
			);
		}

		return \array_map(
			function ( $comment ) {
				return $this->getTimestampColDef( $comment );
			},
			\array_merge(
				$this->cols_timestamps ?? [],
				$standardTsCols
			)
		);
	}

	protected function getPrimaryKeyDef() :string {
		return sprintf( 'PRIMARY KEY  (%s)', $this->getPrimaryKeyColumnName() );
	}

	protected function getTimestampColDef( string $comment = '' ) :string {
		return sprintf( "INT(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT '%s'", \str_replace( "'", '', $comment ) );
	}

	protected function getPrimaryKeyColumnName() :string {
		return $this->primary_key ?? static::PRIMARY_KEY;
	}

	public function hasColumn( string $col ) :bool {
		return \in_array( \strtolower( $col ), $this->getColumnNames() );
	}
}