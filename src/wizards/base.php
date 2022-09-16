<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
use FernleafSystems\Wordpress\Services\Services;

abstract class ICWP_WPSF_Wizard_Base {

	use Shield\Modules\ModConsumer;

	/**
	 * @return array
	 */
	protected function getRenderData_PageWizard() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		return Services::DataManipulation()->mergeArraysRecursive(
			$mod->getUIHandler()->getBaseDisplayData(),
			[
				'hrefs'   => [
					'dashboard'   => $this->getCon()->getPluginUrl_DashboardHome(),
					'goprofooter' => 'https://shsec.io/goprofooter',
				],
			]
		);
	}
}