<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtensionPageContainer;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\ExtPage\TabSitesListing;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class MwpExtensionLoader {

	use PluginControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function run() {
		$req = Services::Request();

		// Adjust the title at the top of the page, so it's not "Wp Simple Firewall"
		add_filter( 'mainwp_header_title', fn() => self::con()->labels->Name, 100, 0 );

		// Render the main extension page content
		echo self::con()->action_router->render( ExtensionPageContainer::SLUG, [
			'current_tab' => empty( $req->query( 'tab' ) ) ? TabSitesListing::TAB : $req->query( 'tab' )
		] );
	}
}