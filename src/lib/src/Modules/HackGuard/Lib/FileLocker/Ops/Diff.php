<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes;

/**
 * Class Diff
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class Diff extends BaseOps {

	/**
	 * @param FileLocker\EntryVO $lock
	 * @return string
	 * @throws \Exception
	 */
	public function run( FileLocker\EntryVO $lock ) {
		$FS = Services::WpFs();

		if ( !$FS->isFile( $lock->file ) ) {
			throw new \Exception( __( 'File is missing or could not be read.', 'wp-simple-firewall' ) );
		}

		$current = $FS->getFileContent( $lock->file );
		if ( empty( $current ) ) {
			throw new \Exception( __( 'File is empty or could not be read.', 'wp-simple-firewall' ) );
		}

		$original = ( new ReadOriginalFileContent() )
			->setMod( $this->getMod() )
			->run( $lock );

		/**
		 * The WP Diff is empty if the only difference is white space
		 * @since v10.3 always use WP Hashes DIFF
		 *
		 * $diff = $this->useWpDiff( $original, $current );
		 * if ( empty( $diff ) ) {
		 *  $this->useWpHashes( $original, $current );
		 * }
		 */

		return $this->useWpHashes( $original, $current );
	}

	/**
	 * @param string $original
	 * @param string $current
	 * @return string
	 * @throws \Exception
	 */
	private function useWpHashes( $original, $current ) :string {
		$res = ( new WpHashes\Util\Diff() )->getDiff( $original, $current );
		if ( !is_array( $res ) || empty( $res[ 'html' ] ) ) {
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
	 * @param string $sOriginal
	 * @param string $sCurrent
	 * @return string
	 */
	private function useWpDiff( $sOriginal, $sCurrent ) {
		return wp_text_diff(
			$sOriginal,
			$sCurrent
		);
	}
}