<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * Shield's marked block inside .htaccess (# comments) or .user.ini (; comments), and detection
 * of any other auto_prepend_file directive in the same file.
 */
final class MarkedBlock {

	public const MARKER = 'Shield Early Loading';

	private string $commentPrefix;

	private string $foreignPrependPattern;

	private function __construct( string $commentPrefix, string $foreignPrependPattern ) {
		$this->commentPrefix = $commentPrefix;
		$this->foreignPrependPattern = $foreignPrependPattern;
	}

	public static function htaccess() :self {
		return new self( '#', '#^[ \t]*php_(?:admin_)?value[ \t]+auto_prepend_file\b#im' );
	}

	public static function userIni() :self {
		return new self( ';', '#^[ \t]*auto_prepend_file[ \t]*=#im' );
	}

	/**
	 * @throws MalformedBlockException
	 */
	public function has( string $content ) :bool {
		$this->assertWellFormed( $content );
		return \preg_match( $this->blockPattern(), $content ) === 1;
	}

	/**
	 * Replaces Shield's block, or appends it when absent.
	 * @throws MalformedBlockException
	 */
	public function apply( string $content, string $body ) :string {
		$this->assertWellFormed( $content );
		$block = \implode( "\n", [
				$this->markerLine( 'BEGIN' ),
				\rtrim( $body ),
				$this->markerLine( 'END' ),
			] )."\n";

		if ( \preg_match( $this->blockPattern(), $content ) === 1 ) {
			return (string)\preg_replace_callback( $this->blockPattern(), fn() => $block, $content, 1 );
		}

		$content = \rtrim( $content, "\r\n" );
		return ( $content === '' ? '' : $content."\n\n" ).$block;
	}

	/**
	 * @throws MalformedBlockException
	 */
	public function remove( string $content ) :string {
		$this->assertWellFormed( $content );
		$removed = (string)\preg_replace( $this->blockPattern(), '', $content );
		return \preg_replace( "#(\r?\n){3,}#", "\n\n", $removed ) ?? $removed;
	}

	/**
	 * Whether the content outside Shield's block sets auto_prepend_file. Comment lines never match.
	 * @throws MalformedBlockException
	 */
	public function hasForeignPrepend( string $content ) :bool {
		return \preg_match( $this->foreignPrependPattern, $this->remove( $content ) ) === 1;
	}

	/**
	 * Edits are refused unless every BEGIN marker has its own END marker, so a damaged block is
	 * never extended or partly removed.
	 * @throws MalformedBlockException
	 */
	private function assertWellFormed( string $content ) :void {
		$begins = \preg_match_all( $this->markerPattern( 'BEGIN' ), $content );
		$ends = \preg_match_all( $this->markerPattern( 'END' ), $content );
		$blocks = \preg_match_all( $this->blockPattern(), $content );
		if ( $begins !== $ends || $begins !== $blocks || $blocks > 1 ) {
			throw new MalformedBlockException( 'The Shield Early Loading markers in this file are incomplete or repeated.' );
		}
	}

	private function markerLine( string $edge ) :string {
		return sprintf( '%s %s %s', $this->commentPrefix, $edge, self::MARKER );
	}

	private function markerPattern( string $edge ) :string {
		return sprintf( '#^[ \t]*%s[ \t]*%s[ \t]+%s[ \t]*\r?$#m',
			\preg_quote( $this->commentPrefix, '#' ), $edge, \preg_quote( self::MARKER, '#' ) );
	}

	private function blockPattern() :string {
		$prefix = \preg_quote( $this->commentPrefix, '#' );
		$marker = \preg_quote( self::MARKER, '#' );
		return sprintf(
			'#^[ \t]*%1$s[ \t]*BEGIN[ \t]+%2$s[ \t]*\r?\n.*?^[ \t]*%1$s[ \t]*END[ \t]+%2$s[ \t]*(?:\r?\n|\z)#ms',
			$prefix, $marker
		);
	}
}
