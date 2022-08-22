<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Options\BuildTransferableOptions;

class RenderOptionsForm {

	use ModConsumer;

	public function render( array $auxParams ) :string {
		$mod = $this->getMod();

		$options = ( new BuildForDisplay() )
			->setMod( $mod )
			->setIsWhitelabelled( $this->getCon()->getModule_SecAdmin()->getWhiteLabelController()->isEnabled() )
			->standard();

		$focusOption = '';
		$focusSection = $mod->getOptions()->getPrimarySection()[ 'slug' ];
		if ( !empty( $auxParams[ 'focus_item' ] ) && !empty( $auxParams[ 'focus_item_type' ] ) ) {
			if ( $auxParams[ 'focus_item_type' ] === 'option' ) {
				$focusOption = $auxParams[ 'focus_item' ];
				$focusSection = $mod->getOptions()->getOptDefinition( $auxParams[ 'focus_item' ] )[ 'section' ];
			}
			elseif ( $auxParams[ 'focus_item_type' ] === 'section' ) {
				$focusSection = $auxParams[ 'focus_item' ];
			}
		}

		try {
			return $mod->getRenderer()
					   ->setTemplate( '/components/options_form/main.twig' )
					   ->setRenderData( [
						   'hrefs' => [
							   'form_action' => 'admin.php?page='.$mod->getModSlug(),
						   ],
						   'vars'  => [
							   'working_mod'   => $mod->getSlug(),
							   'all_options'   => $options,
							   'xferable_opts' => ( new BuildTransferableOptions() )
								   ->setMod( $mod )
								   ->build(),
							   'focus_option'  => $focusOption,
							   'focus_section' => $focusSection,
						   ],
						   'flags' => [
						   ],
					   ] )
					   ->render();
		}
		catch ( \Exception $e ) {
			return 'Error rendering options form: '.$e->getMessage();
		}
	}
}