<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\UI;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\UI\TabRender;
use FernleafSystems\Wordpress\Services\Services;

class ExtensionSettingsPage extends ExecOnceModConsumer {

	protected function run() {

		add_filter( 'shield/custom_enqueues', function ( array $enqueues, $hook ) {
			$con = $this->getCon();
			if ( $con->getModule_Integrations()->getControllerMWP()->isServerExtensionLoaded()
				 && 'mainwp_page_'.$con->mwpVO->extension->page === $hook ) {

				$enqueues[ Enqueue::JS ][] = 'shield/integrations/mainwp-server';
				$enqueues[ Enqueue::CSS ][] = 'shield/integrations/mainwp-server';

//				$handle = 'semantic-ui-datatables-select';
//				wp_register_script(
//					$handle,
//					'https://cdn.datatables.net/select/1.3.1/js/dataTables.select.min.js',
//					[ 'semantic-ui-datatables' ],
//					$con->getVersion(),
//					true
//				);
//				wp_enqueue_script( 'semantic-ui-datatables-select' );
//				wp_register_style(
//					$handle,
//					'https://cdn.datatables.net/select/1.3.1/css/select.dataTables.min.css',
//					[ 'semantic-ui-datatables' ],
//					$con->getVersion()
//				);
//				wp_enqueue_style( 'semantic-ui-datatables-select' );
			}
			return $enqueues;
		}, 10, 2 );
	}

	/**
	 * @throws \Exception
	 */
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

		$currentTab = empty( $req->query( 'tab' ) ) ? 'sites_list' : $req->query( 'tab' );
		if ( !$con->isPremiumActive() ) {
			$pageRenderer = new TabRender\NotShieldPro();
		}
		elseif ( $this->serverPluginNeedsUpdate() ) {
			$pageRenderer = new TabRender\PluginOutOfDate();
		}
		elseif ( !Controller::isMainWPServerVersionSupported() ) {
			$pageRenderer = new TabRender\MwpOutOfDate();
		}
		else {
			/** @var TabRender\BaseTab[] $pages */
			$pages = [
				TabRender\SitesList::class,
				TabRender\SiteManageFrame::class,
			];

			foreach ( $pages as $page ) {
				if ( $currentTab == $page::TAB_SLUG ) {
					$pageRenderer = new $page();
					break;
				}
			}

			if ( empty( $pageRenderer ) ) {
				throw new \Exception( 'Not a supported tab: '.$currentTab );
			}
		}

		try {
			echo $this->getMod()->renderTemplate( '/integration/mainwp/page_extension.twig', [
				'content' => [
					'mainwp_header' => $mainwpHeader,
					'mainwp_footer' => $mainwpFooter,
					'page_inner'    => $pageRenderer->setMod( $this->getMod() )->render(),
				],
			] );
		}
		catch ( \Exception $e ) {
			var_dump( $e->getMessage() );
		}
	}

	private function serverPluginNeedsUpdate() :bool {
		return Services::WpPlugins()->isUpdateAvailable( $this->getCon()->base_file );
	}
}