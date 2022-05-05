<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class AjaxHandler extends Base\AjaxHandler {

	protected function getAjaxActionCallbackMap( bool $isAuth ) :array {
		$map = parent::getAjaxActionCallbackMap( $isAuth );
		if ( $isAuth ) {
			$map = array_merge( $map, [
				'wiz_process_step' => [ $this->getMod()->getWizardHandler(), 'ajaxExec_WizProcessStep' ],
				'wiz_render_step'  => [ $this->getMod()->getWizardHandler(), 'ajaxExec_WizRenderStep' ],
			] );
		}
		return $map;
	}
}