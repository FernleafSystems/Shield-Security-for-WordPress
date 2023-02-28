<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Utility;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class GetLogFileContent {

	use Shield\Modules\AuditTrail\ModConsumer;

	public function run() :string {
		$logFile = $this->opts()->getLogFilePath();
		$FS = Services::WpFs();
		return $FS->isAccessibleFile( $logFile ) ? (string)$FS->getFileContent( $logFile ) : '';
	}
}