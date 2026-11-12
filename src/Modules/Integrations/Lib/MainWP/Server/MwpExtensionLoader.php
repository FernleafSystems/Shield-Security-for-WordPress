<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
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
		// The tab comes from the page URL; it's checked here and passed down, so the pages don't read the request.
		$tab = ActionData::TransportField( $req->query, 'tab' );
		echo self::con()->action_router->render( ExtensionPageContainer::class, [
			'current_tab' => $tab === '' ? TabSitesListing::TAB : $tab,
		] );
	}
}