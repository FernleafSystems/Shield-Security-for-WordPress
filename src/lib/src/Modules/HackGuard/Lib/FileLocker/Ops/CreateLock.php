<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

/**
 * Class CreateLock
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class CreateLock extends BaseOps {

	/**
	 */
	public function create() {
		/** @var FileLocker\Handler $oDbH */
		$oDbH = $this->getDbHandler();
		$oFS = Services::WpFs();
		foreach ( $this->oFile->getPossiblePaths() as $sPossPath ) {
			if ( $oFS->isFile( $sPossPath ) ) {
				$oEntry = new FileLocker\EntryVO();
				$oEntry->file = $sPossPath;
				$oEntry->hash = hash_file( 'sha1', $sPossPath );
				$oEntry->content = base64_encode( $oFS->getFileContent( $sPossPath ) );
				/** @var FileLocker\Insert $oInserter */
				$oInserter = $oDbH->getQueryInserter();
				$oInserter->insert( $oEntry );
				break;
			}
		}
	}
}