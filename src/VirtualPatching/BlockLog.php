<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching;

/**
 * The block log: one JSON line per blocked request, and per request a log rule matched, written by passes that
 * run before Shield can record an event, and later drained into Shield's activity log. The file starts with a PHP
 * exit so it can't be read over the web.
 *
 * Loaded without Composer or WordPress, so it must stay self-contained.
 */
final class BlockLog {

	public const HEADER = "<?php exit; ?>\n";

	/**
	 * Entries are dropped once the log reaches this size, so a flood of blocked requests can't fill the disk.
	 */
	public const MAX_BYTES = 1048576;

	/**
	 * Log rule entries stop once the log reaches this size, so they can never crowd block entries out of it.
	 */
	public const MAX_LOG_BYTES = 262144;

	/**
	 * The log rules this request has already recorded, shared by the early, WordPress and REST passes and each
	 * item of a REST batch, so a rule checked at more than one point is recorded once.
	 *
	 * @var array<string, true>
	 */
	private static array $observed = [];

	/**
	 * Starts a new request's record of log rules, for a process that handles more than one.
	 */
	public static function forgetObservations() :void {
		self::$observed = [];
	}

	/**
	 * Whether this is the first match of the log rule in this request.
	 */
	public static function firstObservation( string $ruleId ) :bool {
		if ( isset( self::$observed[ $ruleId ] ) ) {
			return false;
		}
		self::$observed[ $ruleId ] = true;
		return true;
	}

	/**
	 * The log entry for a blocked request. The IP is read the way the bypass list reads it, falling back to
	 * REMOTE_ADDR when the ruleset names no usable source.
	 */
	public static function entry( array $ruleset, array $rule, array $facts, array $server, string $pass, string $verdict ) :array {
		$remote = $server[ 'REMOTE_ADDR' ] ?? '';
		return [
			'ts'         => \time(),
			'rule'       => (string)( $rule[ 'id' ] ?? '' ),
			'mode'       => self::isLogRule( $rule ) ? 'log' : 'block',
			'advisories' => \is_array( $rule[ 'advisories' ] ?? null ) ? $rule[ 'advisories' ] : [],
			'ip'         => IpBypass::requestIp( $ruleset[ 'bypass' ][ 'ip_source' ] ?? null, $server ) ?? ( \is_string( $remote ) ? $remote : '' ),
			'method'     => $facts[ 'method' ],
			'path'       => $facts[ 'path' ],
			'pass'       => $pass,
			'verdict'    => $verdict,
		];
	}

	public static function append( string $path, array $entry ) :bool {
		$line = \json_encode( $entry, \JSON_UNESCAPED_SLASHES|\JSON_UNESCAPED_UNICODE|\JSON_INVALID_UTF8_SUBSTITUTE );
		if ( !\is_string( $line ) ) {
			return false;
		}
		\clearstatcache( true, $path );
		$exists = \is_file( $path );
		$size = $exists ? @\filesize( $path ) : 0;
		$limit = ( $entry[ 'mode' ] ?? 'block' ) === 'log' ? self::MAX_LOG_BYTES : self::MAX_BYTES;
		if ( $size === false || $size + \strlen( $line ) + 1 > $limit ) {
			return false;
		}
		return @\file_put_contents( $path, ( $exists ? '' : self::HEADER ).$line."\n", \FILE_APPEND|\LOCK_EX ) !== false;
	}

	/**
	 * A log rule records what it would have blocked and lets the request carry on. A rule without a mode, as
	 * written before modes existed, blocks.
	 */
	public static function isLogRule( array $rule ) :bool {
		return ( $rule[ 'mode' ] ?? 'block' ) === 'log';
	}

	/**
	 * Takes every entry out of the log. The file is first renamed, so entries appended meanwhile start a new log.
	 * @return array[]
	 */
	public static function drain( string $path ) :array {
		if ( !\is_file( $path ) ) {
			return [];
		}
		$draining = $path.'.'.\str_replace( '.', '', \uniqid( '', true ) ).'.drain';
		if ( !@\rename( $path, $draining ) ) {
			return [];
		}
		$entries = self::read( $draining );
		@\unlink( $draining );
		return $entries;
	}

	/**
	 * @return array[]
	 */
	public static function read( string $path ) :array {
		$content = @\file_get_contents( $path );
		if ( !\is_string( $content ) ) {
			return [];
		}
		$entries = [];
		foreach ( \explode( "\n", $content ) as $line ) {
			$entry = $line === '' || $line[ 0 ] !== '{' ? null : \json_decode( $line, true );
			if ( \is_array( $entry ) ) {
				$entries[] = $entry;
			}
		}
		return $entries;
	}
}
