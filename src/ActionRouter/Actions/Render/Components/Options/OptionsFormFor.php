<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureGoalPanels;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\BuildOptionsForDisplay;

/**
 * A settings form, always laid out as a Configure topic's panel. The form saves exactly the settings the
 * panel shows: a setting that was asked for but has no place in the panel is neither shown nor saved.
 */
class OptionsFormFor extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_options_form_for';
	public const TEMPLATE = '/components/config/panel_form.twig';

	/**
	 * @throws ActionException
	 */
	protected function getRenderData() :array {
		$ref = ConfigurePanelBuilder::parsePanelRef( $this->action_data[ 'configure_panel' ] );
		if ( $ref === null ) {
			throw new ActionException( 'A settings form must name the Configure panel that lays it out.' );
		}

		$panel = ( new ConfigurePanelBuilder() )->build(
			$ref[ 'goal' ],
			$ref[ 'topic' ],
			$this->buildDisplayOptions( $this->action_data[ 'options' ] )
		);

		$shownKeys = [];
		foreach ( $panel[ 'blocks' ] as $block ) {
			$shownKeys = \array_merge( $shownKeys, ConfigureGoalPanels::flattenItemKeys( $block[ 'items' ] ) );
		}

		return [
			'strings' => [
				'opt_unsaved'               => __( 'Unsaved', 'wp-simple-firewall' ),
				'opt_pro'                   => __( 'Pro', 'wp-simple-firewall' ),
				'opt_edit'                  => __( 'Edit', 'wp-simple-firewall' ),
				'opt_none_selected'         => __( 'None selected', 'wp-simple-firewall' ),
				'opt_help'                  => __( 'Help', 'wp-simple-firewall' ),
				'namespaces_added_by_filter' => __( 'Namespaces added by the filter', 'wp-simple-firewall' ),
				'supply_password'           => __( 'Enter a new value', 'wp-simple-firewall' ),
				'confirm_password'          => __( 'Confirm the new value', 'wp-simple-firewall' ),
				'supply_pin'                => __( 'Enter a new PIN', 'wp-simple-firewall' ),
				'confirm_pin'               => __( 'Confirm your new PIN', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'all_opts_keys' => $shownKeys,
				'panel'         => $panel,
				'form_context'  => $this->action_data[ 'form_context' ],
			],
		];
	}

	/**
	 * Display data for each requested setting, by key. The REST namespaces that other plugins add through the
	 * filter aren't part of the saved setting, so they're listed alongside it rather than in it.
	 * @param list<string> $optionKeys
	 * @return array<string,array<string,mixed>>
	 */
	private function buildDisplayOptions( array $optionKeys ) :array {
		$displayOptions = [];
		// No keys would mean every option to the builder, so an empty request shows nothing.
		if ( !empty( $optionKeys ) ) {
			foreach ( ( new BuildOptionsForDisplay( $optionKeys, [] ) )->standard() as $section ) {
				foreach ( $section[ 'options' ] as $option ) {
					$option[ 'added_by_filter' ] = [];
					$displayOptions[ $option[ 'key' ] ] = $option;
				}
			}
		}
		if ( isset( $displayOptions[ 'api_namespace_exclusions' ] ) ) {
			$lookup = self::con()->comps->opts_lookup;
			$displayOptions[ 'api_namespace_exclusions' ][ 'added_by_filter' ] = \array_values( \array_diff(
				$lookup->getAnonymousRestApiExclusions(),
				$lookup->getAnonymousRestApiExclusions( false )
			) );
		}
		return $displayOptions;
	}

	protected function getRequiredDataKeys() :array {
		return [
			'options',
			'configure_panel',
			'form_context',
		];
	}
}
