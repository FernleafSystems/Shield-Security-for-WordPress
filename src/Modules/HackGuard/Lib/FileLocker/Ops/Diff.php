<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes;

class Diff {

	private const FALLBACK_CONTEXT_LINES = 3;

	public function run( string $original, string $current ) :string {
		/**
		 * The WP Diff is empty if the only difference is white space
		 * @since 10.3 - always use WP Hashes DIFF
		 * @since 12.0 - use WPHashes and fallback to WP Diff
		 */
		try {
			return $this->useWpHashes( $original, $current );
		}
		catch ( \Throwable $e ) {
			// Remote failure selects the local WordPress fallback.
		}

		return $this->useWpDiff( $original, $current );
	}

	private function useWpHashes( string $original, string $current ) :string {
		$res = $this->requestWpHashesDiff( $original, $current );
		$html = $res[ 'html' ] ?? null;
		if ( !\is_array( $html ) ) {
			throw new \UnexpectedValueException();
		}

		$content = $html[ 'content' ] ?? null;
		$css = $html[ 'css_default' ] ?? null;
		if ( !\is_string( $content ) || $content === ''
			 || !\is_string( $css ) || $css === '' ) {
			throw new \UnexpectedValueException();
		}

		$decodedContent = \base64_decode( $content, true );
		$decodedCss = \base64_decode( $css, true );
		if ( !\is_string( $decodedContent ) || $decodedContent === ''
			 || !\is_string( $decodedCss ) || $decodedCss === '' ) {
			throw new \UnexpectedValueException();
		}

		// This HTML is printed as is, deliberately. It comes from our own WP Hashes API (AptoWeb-Api,
		// Requests/WpHashes/Util/Diff.php) over HTTPS, which renders it with jfcherng/php-diff: every line of both
		// files is escaped with htmlspecialchars() before it's placed in the table, so file contents can't add markup.
		// The CSS is that library's static stylesheet. Any cleaning belongs in the API, not here.
		return sprintf( '<style>%s</style>%s',
			'table.diff.diff-wrapper tbody tr td:nth-child(2){ width:auto;}'.
			'table.diff.diff-wrapper { table-layout: auto;}'.
			$decodedCss,
			$decodedContent
		);
	}

	protected function requestWpHashesDiff( string $original, string $current ) :?array {
		return ( new WpHashes\Util\Diff() )->getDiff( $original, $current );
	}

	private function useWpDiff( string $original, string $current ) :string {
		$prepared = ( new PrepareWpDiffInput() )->run( $original, $current );

		try {
			$diff = wp_text_diff( $prepared[ 'original' ], $prepared[ 'current' ], [
				'show_split_view'        => true,
				'leading_context_lines'  => self::FALLBACK_CONTEXT_LINES,
				'trailing_context_lines' => self::FALLBACK_CONTEXT_LINES,
			] );
		}
		catch ( \Throwable $e ) {
			throw new DiffUnavailableException( $e ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal: the exception sets its own fixed message and wraps $e; ScansFileLockerDiff catches it and shows a "diff unavailable" notice instead.
		}

		return $diff;
	}
}
