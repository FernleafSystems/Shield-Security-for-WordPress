<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class RenderOptionsForm {

	use ModConsumer;

	public function render() :string {
		$mod = $this->getMod();

		try {
			return $mod->getRenderer()
					   ->setTemplate(
						   $mod->isAccessRestricted() ? 'subfeature-access_restricted' : '/components/options_form/main.twig'
					   )
					   ->setRenderData( $this->getMod()->getUIHandler()->getBaseDisplayData() )
					   ->render();
		}
		catch ( \Exception $e ) {
			return 'Error rendering options form: '.$e->getMessage();
		}
	}

	public function canDisplayOptionsForm() :bool {
		return !$this->getMod()->cfg->properties[ 'access_restricted' ]
			   || $this->getCon()->isPluginAdmin();
	}
}