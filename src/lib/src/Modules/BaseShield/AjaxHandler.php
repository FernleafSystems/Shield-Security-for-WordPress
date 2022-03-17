<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class AjaxHandler extends Base\AjaxHandler {

	protected function getAjaxActionCallbackMap( bool $isAuth ) :array {
		$map = parent::getAjaxActionCallbackMap( $isAuth );
		if ( $isAuth ) {
			$map = array_merge( $map, [
				'mod_opts_form_render' => [ $this, 'ajaxExec_ModOptionsFormRender' ],
				'mod_options'          => [ $this, 'ajaxExec_ModOptions' ],
				'wiz_process_step'     => [ $this->getMod()->getWizardHandler(), 'ajaxExec_WizProcessStep' ],
				'wiz_render_step'      => [ $this->getMod()->getWizardHandler(), 'ajaxExec_WizRenderStep' ],
			] );
		}
		return $map;
	}

	public function ajaxExec_ModOptions() :array {
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

	public function ajaxExec_ModOptionsFormRender() :array {
		return [
			'success' => true,
			'html'    => $this->getMod()->renderOptionsForm(),
			'message' => 'loaded'
		];
	}
}