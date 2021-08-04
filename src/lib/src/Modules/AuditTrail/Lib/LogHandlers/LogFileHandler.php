<?php declare(strict_types=1);

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\Utility\LogFileRotate;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class LogFileHandler extends StreamHandler {

	use ModConsumer;

	public function __construct( ModCon $modCon, $level = Logger::DEBUG, $bubble = true, $filePermission = null, $useLocking = false ) {
		$this->setMod( $modCon );
		( new LogFileRotate( $this->getLogFilePath() ) )->run();

		parent::__construct( $this->getLogFilePath(), $level, $bubble, $filePermission, $useLocking );
	}

	public function getLogFilePath() :string {
		return $this->getCon()->getPluginCachePath( 'logs/shield.log' );
	}
}