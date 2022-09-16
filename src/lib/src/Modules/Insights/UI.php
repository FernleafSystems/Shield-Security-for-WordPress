<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionRoutingController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Constants;
use FernleafSystems\Wordpress\Services\Services;

class UI extends BaseShield\UI {

	public function printAdminFooterItems() {
		$this->printGoProFooter();
		$this->printToastTemplate();
	}

	private function printGoProFooter() {
		$con = $this->getCon();
		$nav = Services::Request()->query( ActionRoutingController::NAV_ID, Constants::ADMIN_PAGE_OVERVIEW );
		echo $this->getMod()->renderTemplate( 'snippets/go_pro_banner.twig', [
			'flags' => [
				'show_promo' => $con->isModulePage()
								&& !$con->isPremiumActive()
								&& ( !in_array( $nav, [ 'scans_results', 'scans_run' ] ) ),
			],
			'hrefs' => [
				'go_pro' => 'https://shsec.io/shieldgoprofeature',
			]
		] );
	}

	private function printToastTemplate() {
		if ( $this->getCon()->isModulePage() ) {
			echo $this->getMod()->renderTemplate( 'snippets/toaster.twig', [
				'strings'     => [
					'title' => $this->getCon()->getHumanName(),
				],
				'js_snippets' => []
			] );
		}
	}
}