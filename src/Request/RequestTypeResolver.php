<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Request;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\McpCon;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class RequestTypeResolver {

	use PluginControllerConsumer;

	public function resolve() :string {
		$WP = Services::WpGeneral();
		$req = Services::Request();

		if ( $WP->isWpCli() ) {
			$type = Handler::TYPE_WPCLI;
		}
		elseif ( $WP->isAjax() ) {
			$type = Handler::TYPE_AJAX;
		}
		elseif ( Services::Rest()->isRest() ) {
			$type = $this->isShieldMcpRoute( $this->getThisRequest()->getRestRoute() )
				? Handler::TYPE_MCP
				: Handler::TYPE_REST;
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

		return $type;
	}

	public function isShieldAction() :bool {
		$request = Services::Request();
		return $request->request( ActionData::FIELD_ACTION ) === ActionData::FIELD_SHIELD
			   && ActionData::isValidActionSlug( (string)$request->request( ActionData::FIELD_EXECUTE ) );
	}

	private function isShieldMcpRoute( string $restRoute ) :bool {
		$restRoute = \trim( $restRoute, '/' );

		return $restRoute !== ''
			   && \preg_match(
				   '#(?:^|/)'.\preg_quote( McpCon::ROUTE_NAMESPACE, '#' ).'/.*/?'.\preg_quote( McpCon::ROUTE_SEGMENT, '#' ).'$#',
				   $restRoute
			   ) === 1;
	}

	private function getThisRequest() {
		return self::con()->this_req;
	}
}
