<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators;

class SqlIdentifier {

	private const SAFE_IDENTIFIER_REGEX = '#^[A-Za-z0-9_$]+$#';

	public static function assertSafe( string $identifier, string $label = 'SQL identifier' ) :string {
		if ( $identifier === ''
			 || \strpos( $identifier, "\0" ) !== false
			 || \strpos( $identifier, '`' ) !== false
			 || !\preg_match( self::SAFE_IDENTIFIER_REGEX, $identifier )
		) {
			throw new \InvalidArgumentException( \sprintf( '%s is not safe for SQL identifier use.', $label ) );
		}
		return $identifier;
	}

	public static function assertAllowedTable( string $table, ?array $allowedTables = null ) :string {
		self::assertSafe( $table, 'Table name' );
		if ( $allowedTables !== null && !\in_array( $table, $allowedTables, true ) ) {
			throw new \InvalidArgumentException( \sprintf( 'Table is not available for Worpdrive export: %s', $table ) );
		}
		return $table;
	}

	public static function quote( string $identifier, string $label = 'SQL identifier' ) :string {
		return '`'.self::assertSafe( $identifier, $label ).'`';
	}
}
