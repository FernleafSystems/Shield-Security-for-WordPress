<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Encrypt\OpenSslEncryptVo;

/**
 * Class Revert
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpConfig
 */
class Revert extends BaseOps {

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
		$bReverted = Services::WpFs()->putFileContent( $oRecord->file, $sData );
		if ( $bReverted ) {
			/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
			$oMod = $this->getMod();
			/** @var Databases\FileLocker\Handler $oDbH */
			$oDbH = $oMod->getDbHandler_FileLocker();
			/** @var Databases\FileLocker\Update $oUpd */
			$oUpd = $oDbH->getQueryUpdater();
			$oUpd->markReverted( $oRecord );
		}
		return $bReverted;
	}
}