<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset;

/**
 * The ruleset file format: a PHP file returning the ruleset array.
 *
 * The early pass may load this file before WordPress and without Composer, so it must stay self-contained.
 *
 * The hash is the SHA-256 of the JSON encoding (unescaped slashes and unicode) of the ruleset without its
 * 'hash' key. A file that is missing, unreadable, of another format or whose hash does not match is ignored.
 */
final class RulesetFile {

	public const FORMAT = 1;

	public static function hash( array $ruleset ) :string {
		unset( $ruleset[ 'hash' ] );
		return \hash( 'sha256', (string)\json_encode( $ruleset, \JSON_UNESCAPED_SLASHES|\JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * The file content for a ruleset, with its hash added.
	 */
	public static function render( array $ruleset ) :string {
		$ruleset[ 'hash' ] = self::hash( $ruleset );
		return "<?php\n// Shield virtual patching ruleset. Generated: do not edit.\nreturn ".\var_export( $ruleset, true ).";\n";
	}

	public static function read( string $path ) :?array {
		if ( !\is_file( $path ) ) {
			return null;
		}
		try {
			$ruleset = include $path;
		}
		catch ( \Throwable $e ) {
			return null;
		}
		return \is_array( $ruleset )
			   && ( $ruleset[ 'format' ] ?? null ) === self::FORMAT
			   && \is_array( $ruleset[ 'rules' ] ?? null )
			   && \is_string( $ruleset[ 'hash' ] ?? null )
			   && \hash_equals( self::hash( $ruleset ), $ruleset[ 'hash' ] )
			? $ruleset : null;
	}
}
