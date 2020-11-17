<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class AjaxHandler extends Base\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {
		$response = [];
		$mod = $this->getMod();

		switch ( $action ) {

			case 'mod_opts_form_render':
				$response = $this->ajaxExec_ModOptionsFormRender();
				break;

			case 'mod_options':
				$response = $this->ajaxExec_ModOptions();
				break;

			case 'wiz_process_step':
				if ( $mod->hasWizard() ) {
					$response = $mod->getWizardHandler()->ajaxExec_WizProcessStep();
				}
				break;

			case 'wiz_render_step':
				if ( $mod->hasWizard() ) {
					$response = $mod->getWizardHandler()->ajaxExec_WizRenderStep();
				}
				break;

			default:
				$response = parent::processAjaxAction( $action );
		}

		return $response;
	}

	protected function ajaxExec_ModOptions() :array {

		$name = $this->getCon()->getHumanName();

		try {
			$this->getMod()->saveOptionsSubmit();
			$success = true;
			$msg = sprintf( __( '%s Plugin options updated successfully.', 'wp-simple-firewall' ), $name );
		}
		catch ( \Exception $e ) {
			$success = false;
			$msg = sprintf( __( 'Failed to update %s plugin options.', 'wp-simple-firewall' ), $name )
						.' '.$e->getMessage();
		}

		return [
			'success' => $success,
			'html'    => '', //we reload the page
			'message' => $msg
		];
	}

	protected function ajaxExec_ModOptionsFormRender() :array {
		return [
			'success' => true,
			'html'    => $this->getMod()->renderOptionsForm(),
			'message' => 'loaded'
		];
	}
}