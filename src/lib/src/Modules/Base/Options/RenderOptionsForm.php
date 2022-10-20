<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Options\OptionsForm;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class RenderOptionsForm {

	use ModConsumer;

	private $focusOption = '';

	private $focusSection = '';

	public function render( array $auxParams ) :string {
		$mod = $this->getMod();

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
			return $this->getCon()
						->getModule_Insights()
						->getActionRouter()
						->render( OptionsForm::SLUG, [
							'primary_mod_slug' => $mod->getSlug(),
							'all_options'      => ( new BuildForDisplay( $focusSection, $focusOption ) )
								->setMod( $mod )
								->setIsWhitelabelled( $this->getCon()->getModule_SecAdmin()->getWhiteLabelController()->isEnabled() )
								->standard(),
							'focus_option'     => $focusOption,
							'focus_section'    => $focusSection,
							'form_context'     => $auxParams[ 'context' ] ?? 'normal',
						] );
		}
		catch ( \Exception $e ) {
			return 'Error rendering options form: '.$e->getMessage();
		}
	}
}