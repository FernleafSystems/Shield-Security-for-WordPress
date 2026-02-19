<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Common;

use FernleafSystems\Wordpress\Services\Services;

class SqlBackend {

	private static ?bool $sqliteOverride = null;

	public static function isSqlite() :bool {
		if ( self::$sqliteOverride !== null ) {
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
			   || ( \is_object( $wpdb ) && \stripos( \get_class( $wpdb ), 'sqlite' ) !== false );
	}
}
