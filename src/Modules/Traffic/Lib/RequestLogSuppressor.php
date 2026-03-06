<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\AjaxBatchRequests,
	Actions\AjaxRender
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class RequestLogSuppressor {

	use PluginControllerConsumer;

	private const SUPPRESSIBLE_RENDER_ACTIONS = [
		'render_dashboard_live_monitor_ticker',
		'render_traffic_live_logs',
	];

	public function shouldSuppress() :bool {
		return $this->isShieldLiveMonitorNoise() || $this->isLoggedInUsersMeRestNoise();
	}

	private function isShieldLiveMonitorNoise() :bool {
		$req = self::con()->this_req;

		return $req->wp_is_ajax
			   && $req->is_security_admin
			   && $req->path === '/wp-admin/admin-ajax.php'
			   && $this->requestParam( ActionData::FIELD_ACTION ) === ActionData::FIELD_SHIELD
			   && $this->isSuppressibleShieldAction(
				   (string)$this->requestParam( ActionData::FIELD_EXECUTE ),
				   $this->requestParam( 'render_slug' ),
				   $this->requestParam( 'requests' )
			   );
	}

	private function isLoggedInUsersMeRestNoise() :bool {
		$req = self::con()->this_req;

		return Services::WpUsers()->isUserLoggedIn()
			   && $this->requestMethod() === 'GET'
			   && $req->getRestRoute() === 'wp/v2/users/me';
	}

	/**
	 * @param mixed $renderSlug
	 * @param mixed $batchRequests
	 */
	private function isSuppressibleShieldAction( string $actionSlug, $renderSlug, $batchRequests ) :bool {
		return \in_array( $actionSlug, self::SUPPRESSIBLE_RENDER_ACTIONS, true )
			   || ( $actionSlug === AjaxRender::SLUG && $this->isSuppressibleRenderSlug( $renderSlug ) )
			   || ( $actionSlug === AjaxBatchRequests::SLUG && $this->batchContainsOnlySuppressibleRequests( $batchRequests ) );
	}

	/**
	 * @param mixed $batchRequests
	 */
	private function batchContainsOnlySuppressibleRequests( $batchRequests ) :bool {
		if ( !\is_array( $batchRequests ) || empty( $batchRequests ) ) {
			return false;
		}

		foreach ( $batchRequests as $requestItem ) {
			if ( !\is_array( $requestItem ) ) {
				return false;
			}

			$subRequest = $requestItem[ 'request' ] ?? null;
			if ( !\is_array( $subRequest ) ) {
				return false;
			}

			if ( (string)( $subRequest[ ActionData::FIELD_ACTION ] ?? '' ) !== ActionData::FIELD_SHIELD ) {
				return false;
			}

			if ( !$this->isSuppressibleShieldAction(
				(string)( $subRequest[ ActionData::FIELD_EXECUTE ] ?? '' ),
				$subRequest[ 'render_slug' ] ?? null,
				null
			) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param mixed $renderSlug
	 */
	private function isSuppressibleRenderSlug( $renderSlug ) :bool {
		return \is_string( $renderSlug ) && \in_array( $renderSlug, self::SUPPRESSIBLE_RENDER_ACTIONS, true );
	}

	/**
	 * @return mixed
	 */
	private function requestParam( string $key ) {
		$request = self::con()->this_req->request;
		return $request->post[ $key ] ?? $request->query[ $key ] ?? null;
	}

	private function requestMethod() :string {
		return \strtoupper( (string)( self::con()->this_req->request->server[ 'REQUEST_METHOD' ] ?? 'GET' ) );
	}
}
