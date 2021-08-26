<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use Monolog\Processor\ProcessorInterface;

class RequestMetaProcessor implements ProcessorInterface {

	/**
	 * @param array $record
	 * @return array
	 */
	public function __invoke( array $record ) {
		$isWpCli = Services::WpGeneral()->isWpCli();

		$req = Services::Request();
		$record[ 'extra' ][ 'meta_request' ] = array_filter( [
			'ip'         => $isWpCli ? '' : (string)Services::IP()->getRequestIp(),
			'rid'        => Services::Request()->getID( true, 10 ),
			'ts'         => microtime( true ),
			'req_ua'     => $isWpCli ? '' : $req->getUserAgent(),
			'req_method' => $isWpCli ? '' : $req->getMethod(),
			'req_path'   => $isWpCli ? '' : $req->getPath(),
		] );

		return $record;
	}
}
