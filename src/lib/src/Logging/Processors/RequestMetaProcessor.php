<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Logging\Processors;

use FernleafSystems\Wordpress\Services\Services;
use Monolog\Processor\ProcessorInterface;

class RequestMetaProcessor implements ProcessorInterface {

	/**
	 * @return array
	 */
	public function __invoke( array $record ) {
		$isWpCli = Services::WpGeneral()->isWpCli();

		$req = Services::Request();
		$leadingPath = Services::WpGeneral()->isMultisite_SubdomainInstall() ? $req->getHost() : '';

		if ( $isWpCli ) {
			global $argv;
			$path = implode( ' ', $argv );
		}
		else {
			$path = ( $leadingPath.$req->getPath().( empty( $_GET ) ? '' : '?'.http_build_query( $_GET ) ) );
		}

		$record[ 'extra' ][ 'meta_request' ] = [
			'ip'   => $isWpCli ? '' : (string)Services::IP()->getRequestIp(),
			'rid'  => Services::Request()->getID( true, 10 ),
			'ts'   => microtime( true ),
			'ua'   => $isWpCli ? 'wpcli' : $req->getUserAgent(),
			'verb' => $isWpCli ? '' : strtoupper( $req->getMethod() ),
			'path' => $path,
			'code' => $isWpCli ? '' : http_response_code(),
		];

		return $record;
	}
}
