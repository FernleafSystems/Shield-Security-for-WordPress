<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\MainWP\ExtPage\{
	ExtensionPageContainer,
	SitesListing
};
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
		echo $con->getModule_Insights()
				 ->getActionRouter()
				 ->render( ExtensionPageContainer::SLUG, [
					 'current_tab' => empty( $req->query( 'tab' ) ) ? SitesListing::SLUG : $req->query( 'tab' )
				 ] );
	}
}