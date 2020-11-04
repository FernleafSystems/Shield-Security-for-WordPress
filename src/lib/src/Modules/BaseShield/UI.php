<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class UI extends Base\UI {

	public function getBaseDisplayData() :array {
		$con = $this->getCon();
		/** @var \ICWP_WPSF_FeatureHandler_BaseWpsf $mod */
		$mod = $this->getMod();

		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getBaseDisplayData(),
			[
				'head'    => [
					'html'    => [
						'lang' => Services::WpGeneral()->getLocale( '-' ),
						'dir'  => is_rtl() ? 'rtl' : 'ltr',
					],
					'meta'    => [
						[
							'type'      => 'http-equiv',
							'type_type' => 'Cache-Control',
							'content'   => 'no-store, no-cache',
						],
						[
							'type'      => 'http-equiv',
							'type_type' => 'Expires',
							'content'   => '0',
						],
					],
					'scripts' => []
				],
				'ajax'    => [
					'sec_admin_login' => $mod->getSecAdminLoginAjaxData(),
				],
				'flags'   => [
					'show_promo'  => !$con->isPremiumActive(),
					'has_session' => $mod->hasSession()
				],
				'hrefs'   => [
					'aar_forget_key' => $con->getModule_SecAdmin()->isWlEnabled() ?
						$this->getCon()->getLabels()[ 'AuthorURI' ] : 'https://shsec.io/gc'
				],
				'classes' => [
					'top_container' => implode( ' ', array_filter( [
						'odp-outercontainer',
						$this->getCon()->isPremiumActive() ? 'is-pro' : 'is-not-pro',
						$mod->getModSlug(),
						Services::Request()->query( 'inav', '' )
					] ) )
				],
				'vars'    => [
					'related_hrefs' => $this->getSettingsRelatedLinks()
				]
			]
		);
	}

	protected function getSettingsRelatedLinks() :array {
		return [];
	}
}