<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker\EntryVO;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

/**
 * Class Verify
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class Verify {

	/**
	 * @param EntryVO $oRecord
	 * @return bool
	 */
	public function run( $oRecord ) {
		try {
			return ( new CompareHash() )->isEqualFileSha1( $oRecord->file, $oRecord->hash );
		}
		catch ( \InvalidArgumentException $oE ) {
			return false;
		}
	}
}