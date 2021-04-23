<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class UI extends Base\UI {

	public function getBaseDisplayData() :array {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$isWhitelabelled = $con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled();

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
					'sec_admin_login' => $con->getModule_SecAdmin()->getSecAdminLoginAjaxData(),
				],
				'flags'   => [
					'has_session'              => $con->getModule_Sessions()
													  ->getSessionCon()
													  ->hasSession(),
					'display_freshdesk_widget' => !$isWhitelabelled
				],
				'hrefs'   => [
					'aar_forget_key' => $isWhitelabelled ?
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
			]
		);
	}
}