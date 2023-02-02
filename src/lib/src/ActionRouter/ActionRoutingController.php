<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageSecurityAdminRestricted;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\SecurityAdminRequiredException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ActionRoutingController extends ExecOnceModConsumer {

	public const ACTION_AJAX = 1;
	public const ACTION_SHIELD = 2;

	protected function canRun() :bool {
		return true;
	}

	protected function run() {
		$this->captureRedirects();

		( new CaptureShieldAction() )
			->setMod( $this->getMod() )
			->execute();
		( new CaptureAjaxAction() )
			->setMod( $this->getMod() )
			->execute();
	}

	/**
	 * @throws Exceptions\ActionDoesNotExistException
	 * @throws Exceptions\ActionException
	 * @throws Exceptions\ActionTypeDoesNotExistException
	 * @throws Exceptions\SecurityAdminRequiredException
	 */
	public function action( string $slug = '', array $data = [], int $type = self::ACTION_SHIELD ) :ActionResponse {

		switch ( $type ) {
			case self::ACTION_AJAX:
				$adapter = new ResponseAdapter\AjaxResponseAdapter();
				break;
			case self::ACTION_SHIELD:
				$adapter = new ResponseAdapter\ShieldActionResponseAdapter();
				break;
			default:
				throw new Exceptions\ActionTypeDoesNotExistException( $type );
		}

		try {
			$response = ( new ActionProcessor() )
				->setMod( $this->getMod() )
				->processAction( $slug, $data );
		}
		catch ( Exceptions\SecurityAdminRequiredException $sare ) {
			if ( Services::WpGeneral()->isAjax() ) {
				throw $sare;
			}
			$response = $this->action( Actions\Render\PluginAdminPages\PageSecurityAdminRestricted::SLUG, $data );
		}
		catch ( Exceptions\InvalidActionNonceException $e ) {
			wp_die( sprintf( 'Unexpected data. Please try again. Action Slug: "%s"; Data: "%s"', $slug, var_export( $data, true ) ) );
		}

		$adapter->setMod( $this->getMod() )->adapt( $response );
		return $response;
	}

	/**
	 * This is an alias for calling the Render action directly
	 */
	public function render( string $slug, array $data = [] ) :string {
		try {
			$output = $this->action(
				Actions\Render::SLUG,
				[
					'render_action_slug' => $slug,
					'render_action_data' => $data,
				]
			)->action_response_data[ 'render_output' ];
		}
		catch ( SecurityAdminRequiredException $e ) {
			$output = $this->getCon()->action_router->render( PageSecurityAdminRestricted::SLUG );
		}
		catch ( Exceptions\ActionException $e ) {
			$output = $e->getMessage();
		}

		return $output;
	}

	private function captureRedirects() {
		$con = $this->getCon();
		$urls = $con->plugin_urls;
		$req = Services::Request();

		if ( is_admin() && !Services::WpGeneral()->isAjax() ) {

			$redirectTo = null;

			if ( $con->isModulePage() ) {

				$page = (string)$req->query( 'page' );
				$navID = (string)$req->query( Constants::NAV_ID );
				$subNavID = (string)$req->query( Constants::NAV_SUB_ID );

				if ( $page == $urls->rootAdminPageSlug() ) {
					if ( empty( $navID ) ) {
						$redirectTo = $urls->adminHome();
					}
					elseif ( $navID === PluginURLs::NAV_OPTIONS_CONFIG && empty( $subNavID ) ) {
						$redirectTo = $urls->modCfg( $con->getModule_Plugin() );
					}
				}
				else {
					if ( !$urls->isValidNav( $navID ) ) {
						$navID = explode( '-', $page )[ 2 ] ?? '';
					}
					$redirectTo = $urls->isValidNav( $navID ) ? $urls->adminTopNav( $navID ) : $urls->adminHome();
				}
			}
			elseif ( $con->getModule_Plugin()->getActivateLength() < 5 ) {
				$redirectTo = $urls->adminTopNav( PluginURLs::NAV_WIZARD );
			}

			if ( !empty( $redirectTo ) ) {
				Services::Response()->redirect( $redirectTo, [], true, false );
			}
		}
	}
}