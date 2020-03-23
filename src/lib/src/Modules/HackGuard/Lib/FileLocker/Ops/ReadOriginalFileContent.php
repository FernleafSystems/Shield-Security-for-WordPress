<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldSecurityApi\FileLocker\DecryptFile;
use FernleafSystems\Wordpress\Services\Utilities\Encrypt\OpenSslEncryptVo;

/**
 * Class ReadOriginalFileContent
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class ReadOriginalFileContent extends BaseOps {

	/**
	 * @param Databases\FileLocker\EntryVO $oRecord
	 * @return string
	 */
	public function run( $oRecord ) {
		$oVO = ( new OpenSslEncryptVo() )->applyFromArray( json_decode( $oRecord->content, true ) );
		return ( new DecryptFile() )
			->setMod( $this->getMod() )
			->retrieve( $oVO, $oRecord->public_key_id );
	}
}