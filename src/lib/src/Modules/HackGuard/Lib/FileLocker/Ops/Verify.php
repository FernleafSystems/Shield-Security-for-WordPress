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
	 * @param EntryVO $record
	 * @return bool
	 */
	public function run( $record ) {
		try {
			return ( new CompareHash() )->isEqualFileSha1( $record->file, $record->hash );
		}
		catch ( \InvalidArgumentException $e ) {
			return false;
		}
	}
}