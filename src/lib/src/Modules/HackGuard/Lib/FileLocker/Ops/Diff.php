<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes;

class Diff {

	/**
	 * @throws \Exception
	 */
	public function run( FileLockerDB\Record $lock, string $current ) :string {
		$original = ( new ReadOriginalFileContent() )->run( $lock );

		/**
		 * The WP Diff is empty if the only difference is white space
		 * @since 10.3 - always use WP Hashes DIFF
		 * @since 12.0 - use WPHashes and fallback to WP Diff
		 */
		try {
			$diff = $this->useWpHashes( $original, $current );
		}
		catch ( \Exception $e ) {
			$diff = $this->useWpDiff( $original, $current );
		}

		return $diff;
	}

	/**
	 * @param string $original
	 * @param string $current
	 * @throws \Exception
	 */
	private function useWpHashes( $original, $current ) :string {
		$res = ( new WpHashes\Util\Diff() )->getDiff( $original, $current );
		if ( !\is_array( $res ) || empty( $res[ 'html' ] ) ) {
			throw new \Exception( __( 'Could not get a valid diff for this file.', 'wp-simple-firewall' ) );
		}
		return sprintf( '<style>%s</style>%s',
			'table.diff.diff-wrapper tbody tr td:nth-child(2){ width:auto;}'.
			'table.diff.diff-wrapper { table-layout: auto;}'.
			base64_decode( $res[ 'html' ][ 'css_default' ] ),
			base64_decode( $res[ 'html' ][ 'content' ] )
		);
	}

	private function useWpDiff( string $original, string $current ) :string {
		return wp_text_diff( $original, $current );
	}
}
