<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker\EntryVO;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

/**
 * Class Verify
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpConfig
 */
class Verify {

	/**
	 * @param string $sPath
	 * @param string $sHash
	 * @return bool
	 */
	public function run( $sPath, $sHash ) {
		try {
			return ( new CompareHash() )->isEqualFileSha1( $sPath, $sHash );
		}
		catch ( \InvalidArgumentException $oE ) {
			return false;
		}
	}

	/**
	 * @param EntryVO $oRecord
	 * @return bool
	 */
	public function verify( $oRecord ) {
		try {
			return ( new CompareHash() )->isEqualFileSha1( $oRecord->file, $oRecord->hash );
		}
		catch ( \InvalidArgumentException $oE ) {
			return false;
		}
	}
}