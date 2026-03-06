<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogSuppressions\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\AjaxBatchRequests,
	Actions\AjaxRender
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogSuppressions\{
	BaseRule,
	Context
};

class ShieldLiveMonitorAjax extends BaseRule {

	private const SUPPRESSIBLE_RENDER_ACTIONS = [
		'render_dashboard_live_monitor_ticker',
		'render_traffic_live_logs',
	];

	public function matches( Context $context ) :bool {
		if ( !$context->isAjax()
			 || !$context->isSecurityAdmin()
			 || !$context->isShieldTransport()
			 || $context->path() !== '/wp-admin/admin-ajax.php'
		) {
			return false;
		}

		$actionSlug = $context->execute();

		if ( $actionSlug === AjaxRender::SLUG ) {
			return $this->isSuppressibleRenderSlug( $context->renderSlug() );
		}

		return $actionSlug === AjaxBatchRequests::SLUG
			   && $this->batchContainsOnlySuppressibleRequests( $context->batchRequests() );
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

			if ( (string)( $subRequest[ ActionData::FIELD_EXECUTE ] ?? '' ) !== AjaxRender::SLUG ) {
				return false;
			}

			if ( !$this->isSuppressibleRenderSlug( (string)( $subRequest[ 'render_slug' ] ?? '' ) ) ) {
				return false;
			}
		}

		return true;
	}

	private function isSuppressibleRenderSlug( string $renderSlug ) :bool {
		return \in_array( $renderSlug, self::SUPPRESSIBLE_RENDER_ACTIONS, true );
	}
}
