<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureGoalPanels;
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

		$allOptions = $optionsBuilder instanceof BuildOptionsForDisplay ? $optionsBuilder->standard() : [];

		$panel = [];
		$panelRef = $this->getPanelRef();
		if ( $panelRef !== null ) {
			$displayOptions = [];
			foreach ( $allOptions as $section ) {
				foreach ( $section[ 'options' ] as $option ) {
					$displayOptions[ $option[ 'key' ] ] = $option;
				}
			}
			$panel = ( new ConfigurePanelBuilder() )->build( $panelRef[ 'goal' ], $panelRef[ 'topic' ], $displayOptions );
		}

		return [
			'strings' => [
				'inner_page_title'          => __( 'Edit Settings', 'wp-simple-firewall' ),
				'import_export'             => __( 'Import/Export', 'wp-simple-firewall' ),
				'is_opt_importexport'       => __( 'Include this setting during import/export', 'wp-simple-firewall' ),
				'toggle_importexport'       => __( 'Toggle whether this setting is included in import and export operations', 'wp-simple-firewall' ),
				'namespaces_added_by_filter' => __( 'Namespaces added by the filter', 'wp-simple-firewall' ),
				'opt_details'               => __( 'Details', 'wp-simple-firewall' ),
				'opt_default'               => __( 'Default', 'wp-simple-firewall' ),
				'opt_reset_default'         => __( 'Reset to default', 'wp-simple-firewall' ),
				'opt_unsaved'               => __( 'Unsaved', 'wp-simple-firewall' ),
				'opt_pro'                   => __( 'Pro', 'wp-simple-firewall' ),
				'opt_pro_note'              => __( 'This setting is part of the Pro plan. On your current plan it keeps its default value.', 'wp-simple-firewall' ),
				'opt_pro_link'              => __( 'Compare plans', 'wp-simple-firewall' ),
				'opt_edit'                  => __( 'Edit', 'wp-simple-firewall' ),
				'opt_none_selected'         => __( 'None selected', 'wp-simple-firewall' ),
				'opt_help'                  => __( 'Help', 'wp-simple-firewall' ),
				'supply_password'           => __( 'Enter a new value', 'wp-simple-firewall' ),
				'confirm_password'          => __( 'Confirm the new value', 'wp-simple-firewall' ),
			],
			'flags'   => [
				'show_section_navigation' => \count( $allOptions ) > 1,
				'show_transfer_switch'    => (bool)( $this->action_data[ 'show_transfer_switch' ] ?? false ) && $transferAction !== '',
			],
			'imgs'    => [
				'svgs' => [
					'importexport' => $con->svgs->iconClass( 'arrow-down-up' )
				],
			],
			'vars'    => [
				'all_opts_keys'                    => $options,
				'all_options'                      => $allOptions,
				'panel'                            => $panel,
				'filtered_api_namespace_exclusions' => \in_array( 'api_namespace_exclusions', $options, true )
					? $this->getFilteredApiNamespaceExclusions()
					: [],
				'form_context'                     => $this->action_data[ 'form_context' ] ?? 'normal',
				'options_save_action'              => (string)( $this->action_data[ 'options_save_action' ] ?? 'form_save' ),
				'transfer_action'                  => $transferAction,
				'xferable_opts'                    => \is_array( $this->action_data[ 'xferable_opts' ] ?? null )
					? $this->action_data[ 'xferable_opts' ]
					: \array_keys( $con->cfg->configuration->transferableOptions() ),
				'xfer_excluded_opts'               => \is_array( $this->action_data[ 'xfer_excluded_opts' ] ?? null )
					? $this->action_data[ 'xfer_excluded_opts' ]
					: $con->comps->opts_lookup->getXferExcluded(),
			],
		];
	}

	/**
	 * A Configure topic is shown as its own panel layout when it is asked for and its options are here.
	 * @return array{goal:string,topic:string}|null
	 */
	private function getPanelRef() :?array {
		$ref = ConfigurePanelBuilder::parsePanelRef( (string)( $this->action_data[ 'configure_panel' ] ?? '' ) );
		if ( $ref === null ) {
			return null;
		}
		$topicKeys = ConfigureGoalPanels::topicOptionKeys( $ref[ 'goal' ], $ref[ 'topic' ] );
		return empty( \array_intersect( $topicKeys, $this->action_data[ 'options' ] ) ) ? null : $ref;
	}

	protected function getRenderTemplate() :string {
		return $this->getPanelRef() === null ? parent::getRenderTemplate() : '/components/config/panel_form.twig';
	}

	private function getFilteredApiNamespaceExclusions() :array {
		$lookup = self::con()->comps->opts_lookup;

		return \array_values( \array_diff(
			$lookup->getAnonymousRestApiExclusions(),
			$lookup->getAnonymousRestApiExclusions( false )
		) );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'options',
		];
	}
}
