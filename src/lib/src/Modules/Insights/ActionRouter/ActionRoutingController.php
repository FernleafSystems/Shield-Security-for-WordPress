<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\AdminPage;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class ActionRoutingController extends ExecOnceModConsumer {

	public const ACTION_AJAX = 1;
	public const ACTION_SHIELD = 2;

	protected function canRun() :bool {
		return true;
	}

	protected function run() {
		$this->captureRedirects();

		if ( is_admin() || is_network_admin() ) {
			( new AdminPage() )->setMod( $this->getMod() )->execute();
		}

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
			$actionResponse = ( new ActionProcessor() )
				->setMod( $this->getMod() )
				->processAction( $slug, $data );
		}
		catch ( Exceptions\InvalidActionNonceException $e ) {
			wp_die( 'Unexpected data. Please try again.' );
		}

		$adapter->setMod( $this->getMod() )->adapt( $actionResponse );
		return $actionResponse;
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
		catch ( Exceptions\ActionException $e ) {
			$output = $e->getMessage();
		}

		return $output;
	}

	private function captureRedirects() {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();

		$redirectTo = null;

		if ( !Services::WpGeneral()->isAjax() && is_admin() ) {

			$reqPage = (string)$req->query( 'page' );
			if ( $reqPage === $con->prefix()
				 //				 || ( $mod->getAdminPage()->isCurrentPage() && empty( $mod->getCurrentInsightsPage() ) )
				 || ( in_array( $mod->getCurrentInsightsPage(), [ 'dashboard', 'index' ] ) )
			) {
				$redirectTo = $con->getPluginUrl_DashboardHome();
			}
			elseif ( $con->isModulePage() ) {

				// 'insights'
				if ( $reqPage === $mod->getModSlug() ) {
					if ( empty( $req->query( Constants::NAV_ID ) ) ) {
						$redirectTo = $mod->getUrl_SubInsightsPage( Constants::ADMIN_PAGE_OVERVIEW );
					}
					elseif ( $req->query( Constants::NAV_ID ) === Constants::ADMIN_PAGE_CONFIG && empty( $req->query( Constants::NAV_SUB_ID ) ) ) {
						$redirectTo = $mod->getUrl_SubInsightsPage( Constants::ADMIN_PAGE_CONFIG, 'plugin' );
					}
				}
				elseif ( preg_match( sprintf( '#^%s-redirect-([a-z_\-]+)$#', $con->prefix() ), $reqPage, $matches ) ) {
					// Custom wp admin menu items redirect:
					$redirectTo = $mod->getUrl_SubInsightsPage( $matches[ 1 ] );
				}
				else {
					die( 'REDIRECT HERE' );
					$this->redirectToInsightsSubPage();
				}
			}
			elseif ( $con->getModule_Plugin()->getActivateLength() < 5 ) {
				$redirectTo = $mod->getUrl_SubInsightsPage( 'merlin' );
			}
		}

		if ( !empty( $redirectTo ) ) {
			Services::Response()->redirect( $redirectTo, [], true, false );
		}
	}
}