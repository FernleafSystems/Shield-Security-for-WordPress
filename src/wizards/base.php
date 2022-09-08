<?php

use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
use FernleafSystems\Wordpress\Services\Services;

abstract class ICWP_WPSF_Wizard_Base {

	use Shield\Modules\ModConsumer;

	/**
	 * @return array[]
	 */
	protected function getRenderData_PageWizardLanding() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		return Services::DataManipulation()->mergeArraysRecursive(
			$mod->getUIHandler()->getBaseDisplayData(),
			[
				'strings' => [
					'page_title'   => 'Select Your Wizard',
					'premium_note' => 'Note: This uses features only available to Pro-licensed installations.'
				],
				'hrefs'   => [
					'dashboard'   => $this->getCon()->getPluginUrl_DashboardHome(),
					'goprofooter' => 'https://shsec.io/goprofooter',
				],
				'ajax'    => [
					'content'       => $mod->getAjaxActionData( 'wiz_process_step' ),
					'steps'         => $mod->getAjaxActionData( 'wiz_render_step' ),
					'steps_as_json' => $mod->getAjaxActionData( 'wiz_render_step', true ),
				]
			]
		);
	}

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
				'ajax'    => [
					'content'       => $mod->getAjaxActionData( 'wiz_process_step' ),
					'steps'         => $mod->getAjaxActionData( 'wiz_render_step' ),
					'steps_as_json' => $mod->getAjaxActionData( 'wiz_render_step', true ),
				]
			]
		);
	}
}