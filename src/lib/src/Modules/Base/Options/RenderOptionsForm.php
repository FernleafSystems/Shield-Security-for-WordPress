<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Options\BuildTransferableOptions;

class RenderOptionsForm {

	use ModConsumer;

	public function render() :string {
		$mod = $this->getMod();

		try {
			return $mod->getRenderer()
					   ->setTemplate( '/components/options_form/main.twig' )
					   ->setRenderData( [
						   'hrefs' => [
							   'form_action' => 'admin.php?page='.$mod->getModSlug(),
						   ],
						   'vars'  => [
							   'working_mod'   => $mod->getSlug(),
							   'all_options'   => $this->buildOptionsForStandardUI(),
							   'xferable_opts' => ( new BuildTransferableOptions() )
								   ->setMod( $mod )
								   ->build(),
						   ],
					   ] )
					   ->render();
		}
		catch ( \Exception $e ) {
			return 'Error rendering options form: '.$e->getMessage();
		}
	}

	public function buildOptionsForStandardUI() :array {
		return ( new BuildForDisplay() )
			->setMod( $this->getMod() )
			->setIsWhitelabelled( $this->getCon()->getModule_SecAdmin()->getWhiteLabelController()->isEnabled() )
			->standard();
	}
}