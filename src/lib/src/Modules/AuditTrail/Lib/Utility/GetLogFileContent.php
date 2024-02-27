<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class GetLogFileContent {

	use ModConsumer;

	public function run() :string {
		$logFile = self::con()->getModule_AuditTrail()->getAuditCon()->getLogFilePath();
		$FS = Services::WpFs();
		return $FS->isAccessibleFile( $logFile ) ? (string)$FS->getFileContent( $logFile ) : '';
	}
}