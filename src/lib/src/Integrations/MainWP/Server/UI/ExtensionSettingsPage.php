<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\UI;

use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\UI\PageRender\Sites;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ExtensionSettingsPage {

	use PluginControllerConsumer;

	public function render() {
		$con = $this->getCon();

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

//		$sites = apply_filters( 'mainwp_getsites', $con->getRootFile(), $this->childKey );
//		var_dump( $sites );
//		?>
		<!--		https://mainwp.com/passing-information-to-your-child-sites/-->
		<!--		<div id="uploader_select_sites_box" class="mainwp_config_box_right">-->
		<!--        --><?php
//		do_action( 'mainwp_select_sites_box', __( "Select Sites", 'mainwp' ), 'checkbox', true, true, 'mainwp_select_sites_box_right', "", [], [] );
//		?><!--</div>-->
		<!--		--><?php
		$req = Services::Request();

		$currentTab = empty( $req->query( 'tab' ) ) ? 'sites' : $req->query( 'tab' );

		switch ( $currentTab ) {
			case 'sites':
			default:
				$pageRenderer = new Sites();
				break;
		}

		try {
			$con->getRenderer()
				->setTemplateEngineTwig()
				->setTemplate( '/integration/mainwp/page_extension.twig' )
				->setRenderVars( [
					'content' => [
						'mainwp_header' => $mainwpHeader,
						'mainwp_footer' => $mainwpFooter,
						'page_inner'    => $pageRenderer->setCon( $this->getCon() )->render(),
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
				] )
				->display();
		}
		catch ( \Exception $e ) {
			var_dump( $e->getMessage() );
		}
	}
}