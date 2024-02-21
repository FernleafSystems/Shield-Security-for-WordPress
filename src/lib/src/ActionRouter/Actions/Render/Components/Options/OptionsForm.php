<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options\BuildForDisplay;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Options\BuildTransferableOptions;

class OptionsForm extends BaseRender {

	public const SLUG = 'render_options_form';
	public const TEMPLATE = '/components/config/options_form.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$actionData = $this->action_data;
		$mod = $con->modules[ $actionData[ 'mod_slug' ] ];

		$focusOption = $actionData[ 'focus_option' ] ?? '';
		$focusSection = $actionData[ 'focus_section' ] ?? '';
		if ( empty( $focusSection ) ) {
			foreach ( $con->cfg->configuration->sectionsForModule( $actionData[ 'mod_slug' ] ) as $section ) {
				if ( empty( $focusSection ) ) {
					$focusSection = $section[ 'slug' ];
				}
				if ( !empty( $section[ 'primary' ] ) ) {
					$focusSection = $section[ 'slug' ];
					break;
				}
			}
		}

		if ( !empty( $actionData[ 'focus_item' ] ) && !empty( $actionData[ 'focus_item_type' ] ) ) {
			if ( $actionData[ 'focus_item_type' ] === 'option' ) {
				$focusOption = $actionData[ 'focus_item' ];
				$focusSection = $mod->opts()->getOptDefinition( $actionData[ 'focus_item' ] )[ 'section' ];
			}
			elseif ( $actionData[ 'focus_item_type' ] === 'section' ) {
				$focusSection = $actionData[ 'focus_item' ];
			}
		}

		return [
			'strings' => [
				'inner_page_title'    => sprintf( '%s > %s', __( 'Configuration' ), $mod->getDescriptors()[ 'title' ] ),
				'inner_page_subtitle' => $mod->getDescriptors()[ 'subtitle' ],
			],
			'vars'    => [
				'working_mod'   => $mod->cfg->slug,
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
			'mod_slug',
		];
	}
}