<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage\{
	TabSitesListing
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtensionPageContainer;
use FernleafSystems\Wordpress\Services\Services;

class MwpExtensionLoader {

	/**
	 * @throws \Exception
	 */
	public function run() {
		$con = \FernleafSystems\Wordpress\Plugin\Shield\Functions\get_plugin()->getController();
		$req = Services::Request();

		// Adjust the title at the top of the page so it's not "Wp Simple Firewall"
		add_filter( 'mainwp_header_title', function () use ( $con ) {
			return $con->getHumanName();
		}, 100, 0 );

		// Render the main extension page content
		echo $con->action_router->render( ExtensionPageContainer::SLUG, [
			'current_tab' => empty( $req->query( 'tab' ) ) ? TabSitesListing::TAB : $req->query( 'tab' )
		] );
	}
}