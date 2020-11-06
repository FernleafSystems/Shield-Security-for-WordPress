<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\UI;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\UI\PageRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ExtensionSettingsPage {

	use ModConsumer;
	use OneTimeExecute;

	protected function run() {
		add_action( 'admin_enqueue_scripts', function ( $hook ) {
			if ( 'mainwp_page_'.$this->getCon()->mwpVO->extension->page === $hook ) {
				// enqueue your JS
			}
		} );
	}

	public function render() {
		$con = $this->getCon();
		$req = Services::Request();

		// Adjust the title at the top of the page so it's not "Wp Simple Firewall"
		add_filter( 'mainwp_header_title', function () {
			return $this->getCon()->getHumanName();
		}, 100, 0 );

		ob_start();
		do_action( 'mainwp_pageheader_extensions', $this->getCon()->getRootFile() );
		$mainwpHeader = ob_get_contents();
		ob_clean();
		do_action( 'mainwp_pagefooter_extensions', $this->getCon()->getRootFile() );
		$mainwpFooter = ob_get_clean();

		if ( !$con->isPremiumActive() ) {
			$pageRenderer = new PageRender\NotShieldPro();
		}
		elseif ( $this->serverPluginNeedsUpdate() ) {
			$pageRenderer = new PageRender\PluginOutOfDate();
		}
		elseif ( !Controller::isMainWPServerVersionSupported() ) {
			$pageRenderer = new PageRender\MwpOutOfDate();
		}
		else {
			$currentTab = empty( $req->query( 'tab' ) ) ? 'sites' : $req->query( 'tab' );
			switch ( $currentTab ) {
				case 'sites':
				default:
					$pageRenderer = new PageRender\SitesList();
					break;
			}
		}

		try {
			echo $this->getMod()
					  ->renderTemplate(
						  '/integration/mainwp/page_extension.twig',
						  [
							  'content' => [
								  'mainwp_header' => $mainwpHeader,
								  'mainwp_footer' => $mainwpFooter,
								  'page_inner'    => $pageRenderer->setMod( $this->getMod() )->render(),
							  ],
							  'vars'    => [
								  'submenu' => [
									  [
										  'title'  => 'Sites',
										  'href'   => add_query_arg( [ 'tab' => 'sites' ], $req->getUri() ),
										  'icon'   => 'globe',
										  'active' => $currentTab === 'sites',
									  ]
								  ],
							  ]
						  ],
						  true );
		}
		catch ( \Exception $e ) {
			var_dump( $e->getMessage() );
		}
	}

	private function serverPluginNeedsUpdate() :bool {
		return Services::WpPlugins()->isUpdateAvailable(
			$this->getCon()->getPluginBaseFile()
		);
	}
}