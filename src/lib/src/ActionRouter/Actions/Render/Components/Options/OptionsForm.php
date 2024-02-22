<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options\BuildForDisplay;

class OptionsForm extends BaseRender {

	public const SLUG = 'render_options_form';
	public const TEMPLATE = '/components/config/options_form.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$config = $con->cfg->configuration;
		$optsCon = $con->opts;
		$actionData = $this->action_data;
		$mod = $con->modules[ $actionData[ 'mod_slug' ] ];

		$focusOption = $actionData[ 'focus_option' ] ?? '';
		$focusSection = $actionData[ 'focus_section' ] ?? '';
		if ( empty( $focusSection ) ) {
			foreach ( $config->sectionsForModule( $actionData[ 'mod_slug' ] ) as $section ) {
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
				$focusSection = $optsCon->optDef( $actionData[ 'focus_item' ] )[ 'section' ];
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
			'flags'   => [
				'is_wpcli'             => $con->getModule_Plugin()->opts()->isEnabledWpcli(),
				'show_transfer_switch' => $con->isPremiumActive(),
			],
			'vars'    => [
				'all_opts_keys'      => \array_keys( \array_filter(
					$config->optsForModule( $mod->cfg->slug ),
					function ( array $optDef ) {
						return empty( $optDef[ 'hidden' ] );
					}
				) ),
				'all_options'        => ( new BuildForDisplay( $focusSection, $focusOption ) )
					->setMod( $mod )
					->standard(),
				'xferable_opts'      => \array_keys( $config->transferableOptions() ),
				'xfer_excluded_opts' => $optsCon->getXferExcluded(),
				'focus_section'      => $focusSection,
				'focus_option'       => $focusOption,
				'form_context'       => $this->action_data[ 'form_context' ] ?? 'normal',
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'mod_slug',
		];
	}
}