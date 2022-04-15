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
		$isPremium = $this->getCon()->isPremiumActive();

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
							'type_type' => 'Cache-Control',
							'content'   => 'max-age=0',
						],
						[
							'type'      => 'http-equiv',
							'type_type' => 'Expires',
							'content'   => '0',
						],
						[
							'type'      => 'name',
							'type_type' => 'robots',
							'content'   => implode( ',', [ 'noindex', 'nofollow', 'noarchve', 'noimageindex' ] ),
						],
					],
					'scripts' => []
				],
				'ajax'    => [
					'sec_admin_login' => $con->getModule_SecAdmin()->getSecAdminLoginAjaxData(),
				],
				'flags'   => [
					'has_session'             => $mod->getSessionWP()->valid,
					'display_helpdesk_widget' => !$isWhitelabelled,
					'is_whitelabelled'        => $isWhitelabelled
				],
				'hrefs'   => [
					'aar_forget_key' => $isWhitelabelled ?
						$this->getCon()->getLabels()[ 'AuthorURI' ] : 'https://shsec.io/gc'
				],
				'vars'    => [
					'helpscout_beacon_id' => $isPremium ?
						'db2ff886-2329-4029-9452-44587df92c8c'
						: 'aded6929-af83-452d-993f-a60c03b46568'
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