<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Encrypt\OpenSslEncryptVo;

/**
 * Class ReadOriginalFileContent
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class ReadOriginalFileContent extends BaseOps {

	/**
	 * @param Databases\FileLocker\EntryVO $oRecord
	 * @return mixed
	 */
	public function run( $oRecord ) {
		if ( $oRecord->encrypted ) {
			$oVO = ( new OpenSslEncryptVo() )->applyFromArray( json_decode( $oRecord->content, true ) );
			$sData = Services::Encrypt()->openDataVo( $oVO,
				$this->getCon()->getModule_Plugin()->getOpenSslPrivateKey() );
		}
		else {
			$sData = $oRecord->content;
		}
		return $sData;
	}
}