<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Utility;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class GetLogFileContent {

	use ModConsumer;

	public function run() :string {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$logFile = $opts->getLogFilePath();
		$FS = Services::WpFs();
		return $FS->isFile( $logFile ) ? (string)$FS->getFileContent( $logFile ) : '';
	}
}