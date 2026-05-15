<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Logging\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\Request\RequestTypeResolver;
use FernleafSystems\Wordpress\Services\Services;

class RequestMetaProcessor extends BaseMetaProcessor {

	public function __invoke( array $records ) {
		$WP = Services::WpGeneral();
		$isWpCli = $WP->isWpCli();

		$req = Services::Request();
		$leadingPath = Services::WpGeneral()->isMultisite_SubdomainInstall() ? $req->getHost() : '';

		if ( $isWpCli ) {
			global $argv;
			$path = $argv[ 0 ];
			$query = \count( $argv ) === 1 ? '' : \implode( ' ', \array_slice( $argv, 1 ) );
			$hasParams = \count( $argv ) > 1;
		}
		else {
			$path = $leadingPath.$req->getPath();
			$query = empty( $_GET ) ? '' : \http_build_query( $_GET );
			$hasParams = !empty( $_GET ) || !empty( $_POST );
		}

		$type = ( new RequestTypeResolver() )->resolve();

		$data = [
			'ip'   => $isWpCli ? '127.0.0.1' : $req->ip(),
			'rid'  => $req->getID( true ),
			'ts'   => \microtime( true ),
			'path' => $path,
			'type' => $type,
			'has_params' => $hasParams ? 1 : 0,
		];
		if ( !$isWpCli ) {
			$data[ 'ua' ] = sanitize_text_field( $req->getUserAgent() );
			$data[ 'code' ] = \http_response_code();
			$data[ 'verb' ] = \strtoupper( $req->getMethod() );
		}
		if ( !empty( $query ) ) {
			$data[ 'query' ] = $query;
		}

		$records[ 'extra' ][ 'meta_request' ] = $data;

		return $records;
	}
}
