<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	Exceptions\AssetHashesNotFound,
	Exceptions\NoneAssetFileException,
	Exceptions\UnrecognisedAssetFile,
	Query
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Theme\Files;

abstract class BasePluginThemeFile extends BaseScan {

	protected function isSupportedFileExt() :bool {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		$ext = strtolower( (string)pathinfo( $this->pathFull, PATHINFO_EXTENSION ) );
		return !empty( $ext ) && in_array( $ext, $action->file_exts );
	}
}