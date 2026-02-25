<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Common;

use FernleafSystems\Wordpress\Services\Services;

class SqlBackend {

	private static ?bool $sqliteOverride = null;

	public static function isSqlite() :bool {
		if ( self::$sqliteOverride !== null ) {
			// this explicit check is in-case runtime changes affects this.
			return self::$sqliteOverride;
		}
		return self::resolveIsSqlite();
	}

	public static function setSqliteOverrideForTests( ?bool $isSqlite ) :void {
		self::$sqliteOverride = $isSqlite;
	}

	public static function resetForTests() :void {
		self::$sqliteOverride = null;
	}

	private static function resolveIsSqlite() :bool {
		$mysqlInfo = '';
		try {
			$mysqlInfo = Services::WpDb()->getMysqlServerInfo();
		}
		catch ( \Throwable $e ) {
		}

		global $wpdb;
		return ( \stripos( (string)$mysqlInfo, 'sqlite' ) !== false )
			   || self::isSqliteWpdb( $wpdb );
	}

	private static function isSqliteWpdb( $wpdb ) :bool {
		if ( !\is_object( $wpdb ) ) {
			return false;
		}

		if ( self::isWpSqliteClass( $wpdb ) ) {
			return true;
		}

		// In WP Playground, class "wpdb" may extend WP_SQLite_DB and hide SQLite in its class name.
		if ( \class_exists( '\WP_SQLite_DB', false ) && $wpdb instanceof \WP_SQLite_DB ) {
			return true;
		}

		try {
			$dbh = $wpdb->dbh ?? null;
		}
		catch ( \Throwable $e ) {
		}

		return self::isSqliteDbHandle( $dbh );
	}

	private static function isSqliteDbHandle( $dbh ) :bool {
		if ( !\is_object( $dbh ) ) {
			return false;
		}

		if ( \class_exists( '\SQLite3', false ) && $dbh instanceof \SQLite3 ) {
			return true;
		}

		if ( \class_exists( '\PDO', false ) && $dbh instanceof \PDO ) {
			try {
				return \strtolower( (string)$dbh->getAttribute( \PDO::ATTR_DRIVER_NAME ) ) === 'sqlite';
			}
			catch ( \Throwable $e ) {
			}
		}

		return self::isWpSqliteClass( $dbh );
	}

	private static function isWpSqliteClass( object $object ) :bool {
		return \strpos( \strtolower( \ltrim( \get_class( $object ), '\\' ) ), 'wp_sqlite_' ) === 0;
	}
}
