<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options\BuildForDisplay;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Options\BuildTransferableOptions;

class OptionsForm extends BaseRender {

	const SLUG = 'render_options_form';
	const TEMPLATE = '/components/options_form/main.twig';

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {

			case 'primary_mod_slug':
				$value = $this->action_data[ 'primary_mod_slug' ];
				break;

			default:
				break;
		}

		return $value;
	}

	protected function getRenderData() :array {
		$mod = $this->primary_mod;

		$focusOption = $actionData[ 'focus_option' ] ?? '';
		$focusSection = $actionData[ 'focus_section' ] ?? $mod->getOptions()->getPrimarySection()[ 'slug' ];

		$actionData = $this->action_data;
		if ( !empty( $actionData[ 'focus_item' ] ) && !empty( $actionData[ 'focus_item_type' ] ) ) {
			if ( $actionData[ 'focus_item_type' ] === 'option' ) {
				$focusOption = $actionData[ 'focus_item' ];
				$focusSection = $mod->getOptions()->getOptDefinition( $actionData[ 'focus_item' ] )[ 'section' ];
			}
			elseif ( $actionData[ 'focus_item_type' ] === 'section' ) {
				$focusSection = $actionData[ 'focus_item' ];
			}
		}

		return [
			'hrefs' => [
				'form_action' => 'admin.php?page='.$mod->getModSlug(),
			],
			'vars'  => [
				'working_mod'   => $mod->getSlug(),
				'all_options'   => ( new BuildForDisplay( $focusSection, $focusOption ) )
					->setMod( $mod )
					->standard(),
				'xferable_opts' => ( new BuildTransferableOptions() )
					->setMod( $mod )
					->build(),
				'focus_section' => $focusSection,
				'focus_option'  => $focusOption,
				'form_context'  => $this->action_data[ 'form_context' ] ?? 'normal',
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'primary_mod_slug',
		];
	}
}