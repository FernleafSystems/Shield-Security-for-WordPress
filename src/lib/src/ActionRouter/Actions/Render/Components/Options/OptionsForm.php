<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsModules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options\BuildForDisplay;

class OptionsForm extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_options_form';
	public const TEMPLATE = '/components/config/options_form.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$config = $con->cfg->configuration;
		$optsCon = $con->opts;
		$actionData = $this->action_data;
		$modSlug = $actionData[ 'mod_slug' ];

		$focusOption = $actionData[ 'focus_option' ] ?? '';
		$focusSection = $actionData[ 'focus_section' ] ?? '';
		if ( empty( $focusSection ) ) {
			foreach ( $config->sectionsForModule( $modSlug ) as $section ) {
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

		$modStrings = ( new StringsModules() )->getFor( $actionData[ 'mod_slug' ] );
		$secAdminEnabled = $con->comps->sec_admin->isEnabledSecAdmin();
		return [
			'strings' => [
				'inner_page_title'    => sprintf( '%s > %s', __( 'Configuration' ), $modStrings[ 'name' ] ),
				'inner_page_subtitle' => $modStrings[ 'subtitle' ],
				'is_opt_importexport' => __( 'Toggle whether this option is included with import/export', 'wp-simple-firewall' ),

				'supply_password'  => $secAdminEnabled ? __( 'Update PIN', 'wp-simple-firewall' ) : __( 'Supply New PIN', 'wp-simple-firewall' ),
				'confirm_password' => $secAdminEnabled ? __( 'Confirm Updated PIN', 'wp-simple-firewall' ) : __( 'Confirm PIN', 'wp-simple-firewall' ),
			],
			'flags'   => [
				'is_wpcli'             => $con->isPremiumActive()
										  && apply_filters( 'shield/enable_wpcli', $optsCon->optIs( 'enable_wpcli', 'Y' ) ),
				'show_transfer_switch' => $con->isPremiumActive(),
			],
			'vars'    => [
				'all_opts_keys'      => \array_keys( \array_filter(
					$config->optsForModule( $modSlug ),
					function ( array $optDef ) {
						return $optDef[ 'section' ] !== 'section_hidden';
					}
				) ),
				'all_options'        => ( new BuildForDisplay( $modSlug, $focusSection, $focusOption ) )->standard(),
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