<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Common;

class IpAddressSql {

	private static ?bool $inetPtonAvailableOverride = null;

	public static function literalFromIp( string $ip ) :string {
		return SqlBackend::isSqlite() ? self::literalFromIpForSqlite( $ip ) : self::literalFromIpForMysql( $ip );
	}

	public static function equality( string $column, string $ip ) :string {
		return \sprintf( '%s=%s', $column, self::literalFromIp( $ip ) );
	}

	public static function literalsFromIps( array $ips ) :array {
		return \array_map(
			fn( string $eachIp ) => self::literalFromIp( $eachIp ),
			\array_values( \array_filter( $ips, '\is_string' ) )
		);
	}

	public static function setInetPtonAvailableOverrideForTests( ?bool $isAvailable ) :void {
		self::$inetPtonAvailableOverride = $isAvailable;
	}

	public static function resetForTests() :void {
		self::$inetPtonAvailableOverride = null;
		SqlBackend::resetForTests();
	}

	private static function literalFromIpForMysql( string $ip ) :string {
		return \sprintf( 'INET6_ATON(%s)', self::quoteString( $ip ) );
	}

	private static function literalFromIpForSqlite( string $ip ) :string {
		$trimmed = \trim( $ip );
		if ( $trimmed === '' || \filter_var( $trimmed, \FILTER_VALIDATE_IP ) === false ) {
			return 'NULL';
		}

		$binary = self::ipToBinary( $trimmed );
		return $binary === null ? 'NULL' : \sprintf( "X'%s'", \bin2hex( $binary ) );
	}

	private static function ipToBinary( string $ip ) :?string {
		$isInetPtonAvailable = self::$inetPtonAvailableOverride ?? \function_exists( 'inet_pton' );
		if ( !$isInetPtonAvailable ) {
			return null;
		}

		try {
			$binary = \inet_pton( $ip );
		}
		catch ( \Throwable $e ) {
			$binary = false;
		}

		return ( \is_string( $binary ) && \in_array( \strlen( $binary ), [ 4, 16 ], true ) ) ? $binary : null;
	}

	private static function quoteString( string $value ) :string {
		return \sprintf( "'%s'", \str_replace( "'", "''", $value ) );
	}
}
