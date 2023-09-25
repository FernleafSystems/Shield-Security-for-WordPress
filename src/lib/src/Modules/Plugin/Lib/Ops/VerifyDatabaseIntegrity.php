<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class VerifyDatabaseIntegrity {

	use PluginControllerConsumer;

	public function run() {
		$this->verifyForeignKeys();
	}

	/**
	 * Seeks to find DB tables where the registered foreign keys are invalid. This can happen, it seems, if db tables
	 * are renamed after they've been created, and the foreign key restraints reference tables that no longer exist.
	 */
	private function verifyForeignKeys() {
		$WPDB = Services::WpDb();

		$tablesToDelete = [];
		foreach ( self::con()->db_con->loadAll() as $dbhDef ) {
			/** @var Handler $dbh */
			$dbh = $dbhDef[ 'handler' ];

			$schema = $dbh->getTableSchema();
			foreach ( $schema->getColumnsDefs() as $col => $def ) {
				if ( !empty( $def[ 'foreign_key' ] ) ) {

					$fk = $def[ 'foreign_key' ];
					$ft = sprintf( '%s%s', ( $fk[ 'wp_prefix' ] ?? true ) ? $WPDB->getPrefix() : '', $fk[ 'ref_table' ] );

					$constraintFound = false;
					foreach ( $this->getForeignKeyConstraintsOn( $ft ) as $fkConstraint ) {
						$constraintFound = ( $fkConstraint[ 'TABLE_NAME' ] ?? '' ) === $schema->table
										   && ( $fkConstraint[ 'COLUMN_NAME' ] ?? '' ) === $col;
						if ( $constraintFound ) {
							break;
						}
					}
					if ( !$constraintFound ) {
						$tablesToDelete[] = $schema->table;
					}
				}
			}
		}

		if ( !empty( $tablesToDelete ) ) {
			error_log( sprintf( 'invalid foreign key configuration found. Dropping Shield tables: %s',
				\implode( ",", $tablesToDelete ) ) );
			$WPDB->doSql( sprintf( 'DROP TABLE IF EXISTS `%s`', \implode( "`,`", $tablesToDelete ) ) );
		}
	}

	/**
	 * array(5) {
	 * ["TABLE_NAME"]=>
	 * string(25) "test1_icwp_wpsf_botsignal"
	 * ["COLUMN_NAME"]=>
	 * string(6) "ip_ref"
	 * ["CONSTRAINT_NAME"]=>
	 * string(32) "test1_icwp_wpsf_botsignal_ibfk_1"
	 * ["REFERENCED_TABLE_NAME"]=>
	 * string(19) "test1_icwp_wpsf_ips"
	 * ["REFERENCED_COLUMN_NAME"]=>
	 * string(2) "id"
	 * }
	 */
	private function getForeignKeyConstraintsOn( string $table ) :array {
		$DB = Services::WpDb();
		$data = $DB->selectCustom( sprintf( "
			SELECT 
			    TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME
			FROM
			    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
			WHERE
			      REFERENCED_TABLE_SCHEMA = '%s' AND REFERENCED_TABLE_NAME = '%s';
			      ",
				DB_NAME, $table )
		);
		return \is_array( $data ) ? $data : [];
	}
}
