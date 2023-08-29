<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\MainwpBase;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\MWPSiteVO;
use MainWP\Dashboard\MainWP_Connect;

abstract class BaseSiteMwpAction extends MainwpBase {

	/**
	 * @var mixed
	 */
	protected $clientActionResponse;

	protected function exec() {
		try {
			$success = $this->doClientSitePluginAction();
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
				self::con()->action_router->action( SiteActionSync::class, $this->action_data );
			}
			catch ( ActionException $e ) {
				error_log( $e->getMessage() );
			}
		}
	}

	protected function isPostSyncRequired() :bool {
		return ( $this->response()->action_response_data[ 'success' ] ?? false )
			   && !\in_array( static::SLUG, [ SiteActionSync::SLUG, SiteActionDeactivate::SLUG ] );
	}

	/**
	 * @throws ActionException
	 */
	protected function doClientSitePluginAction() :bool {
		$this->clientActionResponse = $this->fireClientSiteAction();
		return $this->checkResponse();
	}

	protected function fireClientSiteAction() {
		return MainWP_Connect::fetch_url_authed(
			$this->getMwpSite()->siteobj,
			$this->getMainwpActionSlug(),
			$this->getMainwpActionParams()
		);
	}

	/**
	 * @throws ActionException
	 */
	protected function checkResponse() :bool {
		return ( $this->clientActionResponse[ 'status' ] ?? false ) === 'SUCCESS';
	}

	protected function getMainwpActionSlug() :string {
		return 'plugin_action';
	}

	protected function getMainwpActionParams() :array {
		return [];
	}

	protected function getMainwpActionFailureMessage() :string {
		return __( 'Client site action failed.', 'wp-simple-firewall' );
	}

	protected function getMainwpActionSuccessMessage() :string {
		return __( 'Client site action was successful', 'wp-simple-firewall' );
	}

	protected function getMwpSite() :MWPSiteVO {
		return MWPSiteVO::LoadByID( (int)$this->action_data[ 'client_site_id' ] );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'client_site_id'
		];
	}
}