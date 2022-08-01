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
					'aar_forget_key' => $con->labels->url_secadmin_forgotten_key
				],
				'vars'    => [
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