<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes;

class Diff extends BaseOps {

	/**
	 * @throws \Exception
	 */
	public function run( FileLockerDB\Record $lock ) :string {
		$FS = Services::WpFs();

		if ( !$FS->isAccessibleFile( $lock->path ) ) {
			throw new \Exception( __( 'File is missing or could not be read.', 'wp-simple-firewall' ) );
		}

		$current = $FS->getFileContent( $lock->path );
		if ( empty( $current ) ) {
			throw new \Exception( __( 'File is empty or could not be read.', 'wp-simple-firewall' ) );
		}

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
			throw new \Exception( 'Could not get a valid diff for this file.' );
		}
		return sprintf( '<style>%s</style>%s',
			'table.diff.diff-wrapper tbody tr td:nth-child(2){ width:auto;}'.
			'table.diff.diff-wrapper { table-layout: auto;}'.
			base64_decode( $res[ 'html' ][ 'css_default' ] ),
			base64_decode( $res[ 'html' ][ 'content' ] )
		);
	}

	/**
	 * @param string $original
	 * @param string $current
	 */
	private function useWpDiff( $original, $current ) :string {
		return wp_text_diff( $original, $current );
	}
}