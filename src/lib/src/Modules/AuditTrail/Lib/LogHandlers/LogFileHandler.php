<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers;

use AptowebDeps\Monolog\Handler\StreamHandler;
use AptowebDeps\Monolog\Logger;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @deprecated 19.2
 */
class LogFileHandler extends StreamHandler {

	use PluginControllerConsumer;

	public function __construct( $level = Logger::DEBUG, $bubble = true, $filePermission = null, $useLocking = false ) {
		$path = self::con()->comps->activity_log->getLogFilePath();

		parent::__construct( $path, $level, $bubble, $filePermission, $useLocking );
	}
}