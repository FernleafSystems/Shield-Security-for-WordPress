<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\MainwpBase;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\MWPSiteVO;
use MainWP\Dashboard\MainWP_Connect;

abstract class BaseSiteMwpAction extends MainwpBase {

	protected function exec() {
		try {
			$success = $this->fireMainwpSitePluginAction();
			$response = [
				'success' => $success,
				'message' => $success ? $this->getMainwpActionSuccessMessage() : $this->getMainwpActionFailureMessage(),
			];
		}
		catch ( \Exception $e ) {
			$response = [
				'success' => false,
				'message' => $e->getMessage()
			];
		}

		$this->response()->action_response_data = $response;
	}

	/**
	 * Run a site sync after any action that requires it.
	 */
	protected function postExec() {
		if ( $this->isPostSyncRequired() ) {
			try {
				$this->getCon()->action_router->action( SiteActionSync::SLUG, $this->action_data );
			}
			catch ( ActionException $e ) {
				error_log( $e->getMessage() );
			}
		}
	}

	protected function isPostSyncRequired() :bool {
		return ( $this->response()->action_response_data[ 'success' ] ?? false )
			   && !in_array( static::SLUG, [ SiteActionSync::SLUG, SiteActionDeactivate::SLUG ] );
	}

	protected function fireMainwpSitePluginAction() :bool {
		return $this->isResultSuccess(
			MainWP_Connect::fetch_url_authed(
				$this->getMwpSite()->siteobj,
				$this->getMainwpActionSlug(),
				$this->getMainwpActionParams()
			)
		);
	}

	protected function isResultSuccess( array $result ) :bool {
		return ( $result[ 'status' ] ?? false ) === 'SUCCESS';
	}

	protected function getMainwpActionSlug() :string {
		return 'plugin_action';
	}

	protected function getMainwpActionParams() :array {
		return [];
	}

	protected function getMainwpActionFailureMessage() :string {
		return 'Action Failed';
	}

	protected function getMainwpActionSuccessMessage() :string {
		return 'Action Successful';
	}

	protected function getMwpSite() :MWPSiteVO {
		return MWPSiteVO::LoadByID( (int)$this->action_data[ 'site_id' ] );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'site_id'
		];
	}
}