<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\BuildOptionsForDisplay;

class OptionsFormFor extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_options_form_for';
	public const TEMPLATE = '/components/config/options_form_for.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$options = $this->action_data[ 'options' ];
		$configItem = (string)( $this->action_data[ 'config_item' ] ?? '' );
		$transferAction = (string)( $this->action_data[ 'transfer_action' ] ?? '' );
		$optionsBuilder = empty( $options ) ? null : ( new BuildOptionsForDisplay( $options, [] ) )
			->setValues( \is_array( $this->action_data[ 'values' ] ?? null ) ? $this->action_data[ 'values' ] : [] )
			->setFocusOption( $configItem );

		if ( $optionsBuilder instanceof BuildOptionsForDisplay ) {
			$configItemDef = $con->cfg->configuration->options[ $configItem ] ?? [];
			if ( !empty( $configItemDef[ 'section' ] ) ) {
				$optionsBuilder->setFocusSection( (string)$configItemDef[ 'section' ] );
			}
		}

		return [
			'strings' => [
				'inner_page_title'    => __( 'Edit Settings', 'wp-simple-firewall' ),
				'import_export'       => __( 'Import/Export', 'wp-simple-firewall' ),
				'is_opt_importexport' => __( 'Include this setting during import/export', 'wp-simple-firewall' ),
				'toggle_importexport' => __( 'Toggle whether this setting is included in import and export operations', 'wp-simple-firewall' ),
			],
			'flags'   => [
				'show_transfer_switch' => (bool)( $this->action_data[ 'show_transfer_switch' ] ?? false ) && $transferAction !== '',
			],
			'imgs'    => [
				'svgs' => [
					'importexport' => $con->svgs->iconClass( 'arrow-down-up' )
				],
			],
			'vars'    => [
				'all_opts_keys'      => $options,
				'all_options'        => $optionsBuilder instanceof BuildOptionsForDisplay ? $optionsBuilder->standard() : [],
				'form_context'       => $this->action_data[ 'form_context' ] ?? 'normal',
				'options_save_action' => (string)( $this->action_data[ 'options_save_action' ] ?? 'form_save' ),
				'transfer_action'    => $transferAction,
				'xferable_opts'      => \is_array( $this->action_data[ 'xferable_opts' ] ?? null )
					? $this->action_data[ 'xferable_opts' ]
					: \array_keys( $con->cfg->configuration->transferableOptions() ),
				'xfer_excluded_opts' => \is_array( $this->action_data[ 'xfer_excluded_opts' ] ?? null )
					? $this->action_data[ 'xfer_excluded_opts' ]
					: $con->comps->opts_lookup->getXferExcluded(),
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'options',
		];
	}
}
