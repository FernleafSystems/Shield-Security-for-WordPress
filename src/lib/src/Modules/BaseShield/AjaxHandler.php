<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class AjaxHandler extends Base\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {
		$aResponse = [];
		$mod = $this->getMod();

		switch ( $action ) {

			case 'mod_opts_form_render':
				$aResponse = $this->ajaxExec_ModOptionsFormRender();
				break;

			case 'mod_options':
				$aResponse = $this->ajaxExec_ModOptions();
				break;

			case 'wiz_process_step':
				if ( $mod->hasWizard() ) {
					$aResponse = $mod->getWizardHandler()
									 ->ajaxExec_WizProcessStep();
				}
				break;

			case 'wiz_render_step':
				if ( $mod->hasWizard() ) {
					$aResponse = $mod->getWizardHandler()
									 ->ajaxExec_WizRenderStep();
				}
				break;

			default:
				$aResponse = parent::processAjaxAction( $action );
		}

		return $aResponse;
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_ModOptions() {

		$sName = $this->getCon()->getHumanName();

		try {
			$this->getMod()->saveOptionsSubmit();
			$bSuccess = true;
			$sMessage = sprintf( __( '%s Plugin options updated successfully.', 'wp-simple-firewall' ), $sName );
		}
		catch ( \Exception $oE ) {
			$bSuccess = false;
			$sMessage = sprintf( __( 'Failed to update %s plugin options.', 'wp-simple-firewall' ), $sName )
						.' '.$oE->getMessage();
		}

		return [
			'success' => $bSuccess,
			'html'    => '', //we reload the page
			'message' => $sMessage
		];
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_ModOptionsFormRender() {
		return [
			'success' => true,
			'html'    => $this->getMod()->renderOptionsForm(),
			'message' => 'loaded'
		];
	}
}