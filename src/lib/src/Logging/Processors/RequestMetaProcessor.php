<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Logging\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops\Handler;
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
		}
		else {
			$path = $leadingPath.$req->getPath();
			$query = empty( $_GET ) ? '' : \http_build_query( $_GET );
		}

		if ( $isWpCli ) {
			$type = Handler::TYPE_WPCLI;
		}
		elseif ( $WP->isAjax() ) {
			$type = Handler::TYPE_AJAX;
		}
		elseif ( Services::Rest()->isRest() ) {
			$type = Handler::TYPE_REST;
		}
		elseif ( $WP->isXmlrpc() ) {
			$type = Handler::TYPE_XMLRPC;
		}
		elseif ( $WP->isCron() ) {
			$type = Handler::TYPE_CRON;
		}
		elseif ( $WP->isLoginRequest() ) {
			$type = Handler::TYPE_LOGIN;
		}
		elseif ( $WP->isLoginUrl()
				 && $req->isPost()
				 && $req->query( ActionData::FIELD_EXECUTE ) === ActionData::FIELD_SHIELD.'-wp_login_2fa_verify'
		) {
			$type = Handler::TYPE_2FA;
		}
		elseif ( Services::WpComments()->isCommentSubmission() ) {
			$type = Handler::TYPE_COMMENT;
		}
		else {
			$type = Handler::TYPE_HTTP;
		}

		$data = [
			'ip'   => $isWpCli ? '127.0.0.1' : $req->ip(),
			'rid'  => $req->getID( true ),
			'ts'   => \microtime( true ),
			'path' => $path,
			'type' => $type,
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