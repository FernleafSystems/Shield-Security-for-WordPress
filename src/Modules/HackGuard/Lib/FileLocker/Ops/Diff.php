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
			throw new DiffUnavailableException( $e );
		}

		return $diff;
	}
}
