<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching;

/**
 * The block log: one JSON line per blocked request, written by passes that run before Shield can record an
 * event, and later drained into Shield's activity log. The file starts with a PHP exit so it can't be read over
 * the web.
 *
 * Loaded without Composer or WordPress, so it must stay self-contained.
 */
final class BlockLog {

	public const HEADER = "<?php exit; ?>\n";

	/**
	 * Entries are dropped once the log reaches this size, so a flood of blocked requests can't fill the disk.
	 */
	public const MAX_BYTES = 1048576;

	public static function append( string $path, array $entry ) :bool {
		$line = \json_encode( $entry, \JSON_UNESCAPED_SLASHES|\JSON_UNESCAPED_UNICODE|\JSON_INVALID_UTF8_SUBSTITUTE );
		if ( !\is_string( $line ) ) {
			return false;
		}
		\clearstatcache( true, $path );
		$exists = \is_file( $path );
		$size = $exists ? @\filesize( $path ) : 0;
		if ( $size === false || $size + \strlen( $line ) + 1 > self::MAX_BYTES ) {
			return false;
		}
		return @\file_put_contents( $path, ( $exists ? '' : self::HEADER ).$line."\n", \FILE_APPEND|\LOCK_EX ) !== false;
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
