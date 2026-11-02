<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureGoalPanels;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

/**
 * Turns a topic's panel layout into render data: each item gets its option's display data (name, summary,
 * value, choices, Pro availability) plus what its control needs, such as a slider's stops.
 */
class ConfigurePanelBuilder {

	use PluginControllerConsumer;

	/**
	 * @var array<string,list<array{text:string,severity:string}>>
	 */
	private array $issuesByOption = [];

	/**
	 * @return array{goal:string,topic:string}|null
	 */
	public static function parsePanelRef( string $ref ) :?array {
		$parts = \explode( ':', $ref );
		if ( \count( $parts ) !== 2 ) {
			return null;
		}
		[ $goal, $topic ] = $parts;
		return isset( ConfigureGoalPanels::goals()[ $goal ][ 'topics' ][ $topic ] ) ? [ 'goal' => $goal, 'topic' => $topic ] : null;
	}

	/**
	 * @param array<string,array<string,mixed>> $displayOptions option key => display data from BuildOptionsForDisplay
	 */
	public function build( string $goalKey, string $topicKey, array $displayOptions ) :array {
		$topic = ConfigureGoalPanels::goals()[ $goalKey ][ 'topics' ][ $topicKey ];
		$labels = ConfigureGoalPanels::labels();
		$issues = $this->topicIssues( \array_keys( $displayOptions ) );
		// Advanced options gathers odd settings from across the goal; problems none of them fix belong
		// with the topics they came from.
		if ( $topicKey === 'advanced' ) {
			$issues[ 'notices' ] = [];
		}
		$this->issuesByOption = $issues[ 'by_option' ];

		$blocks = [];
		foreach ( $topic[ 'blocks' ] as $block ) {
			$items = $this->buildItems( $block[ 'items' ], $displayOptions );
			if ( !empty( $items ) ) {
				$blocks[] = [
					// Advanced options head each group of settings with the topic it belongs to.
					'heading' => $labels[ 'headings' ][ $block[ 'heading' ] ?? '' ]
								 ?? $labels[ 'topics' ][ $block[ 'heading' ] ?? '' ][ 'label' ] ?? '',
					'items'   => $items,
				];
			}
		}

		return [
			'goal'    => $goalKey,
			'topic'   => $topicKey,
			'blocks'  => $blocks,
			'notices' => $issues[ 'notices' ],
		];
	}

	/**
	 * Why each setting needs attention, from the statuses of the components behind the topic. An explanation
	 * that names the setting which fixes it is shown on that setting; any others are shown at the top of
	 * the panel. Explanations fixed by a setting in another topic are left to that topic.
	 * @param list<string> $optionKeys
	 * @return array{
	 *   by_option:array<string,list<array{text:string,severity:string}>>,
	 *   notices:list<array{text:string,severity:string}>
	 * }
	 */
	private function topicIssues( array $optionKeys ) :array {
		$options = self::con()->cfg->configuration->options;
		$slugs = [];
		foreach ( $optionKeys as $optionKey ) {
			foreach ( $options[ $optionKey ][ 'zone_comp_slugs' ] ?? [] as $slug ) {
				if ( \strpos( (string)$slug, 'module_' ) !== 0 && !\in_array( $slug, $slugs, true ) ) {
					$slugs[] = (string)$slug;
				}
			}
		}

		$byOption = [];
		$notices = [];
		$seen = [];
		foreach ( $slugs as $slug ) {
			try {
				$rows = self::con()->comps->zones->getZoneComponent( $slug )->configureRows();
			}
			catch ( \Exception $e ) {
				continue;
			}
			foreach ( $rows as $row ) {
				if ( empty( \array_intersect( $row[ 'config_scope' ][ 'option_keys' ], $optionKeys ) ) ) {
					continue;
				}
				$severity = EnumEnabledStatus::toSeverity( $row[ 'enabled_status' ], 'good' );
				if ( !\in_array( $severity, [ 'warning', 'critical' ], true ) ) {
					continue;
				}
				// Explanations record the setting that fixes them as they're built, so read the lookup afterwards.
				$fixLookup = Component\Base::explanationFixLookup();
				foreach ( $row[ 'explanations' ] as $explanation ) {
					$text = \trim( (string)$explanation );
					if ( $text === '' || isset( $seen[ $text ] ) ) {
						continue;
					}
					$seen[ $text ] = true;
					$fixedBy = $fixLookup[ $text ] ?? $this->soleSettingFor( $row, $optionKeys );
					$issue = [ 'text' => $text, 'severity' => $severity ];
					if ( $fixedBy === '' ) {
						$notices[] = $issue;
					}
					elseif ( \in_array( $fixedBy, $optionKeys, true ) ) {
						$byOption[ $fixedBy ][] = $issue;
					}
				}
			}
		}

		return [ 'by_option' => $byOption, 'notices' => $notices ];
	}

	/**
	 * A reason that doesn't name the setting that fixes it still belongs with its component's setting, when that
	 * component has just the one setting here.
	 * @param array{config_scope:array{option_keys:list<string>}} $row
	 * @param list<string> $optionKeys
	 */
	private function soleSettingFor( array $row, array $optionKeys ) :string {
		$here = \array_values( \array_intersect( $row[ 'config_scope' ][ 'option_keys' ], $optionKeys ) );
		return \count( $here ) === 1 ? $here[ 0 ] : '';
	}

	private function buildItems( array $layoutItems, array $displayOptions ) :array {
		$items = [];
		foreach ( $layoutItems as $layoutItem ) {
			$option = $displayOptions[ $layoutItem[ 'key' ] ] ?? null;
			if ( !\is_array( $option ) ) {
				continue;
			}
			$control = $layoutItem[ 'control' ];
			if ( $control === 'segmented' && !$this->segmentsFitOneLine( $option ) ) {
				$control = 'radios';
			}
			$item = [
				'key'      => $layoutItem[ 'key' ],
				'control'  => $control,
				'option'   => $option,
				// Choices that need room sit beneath their name; everything else sits beside it.
				'stacked'  => \in_array( $control, [ 'chips', 'roles' ], true )
							  || ( $layoutItem[ 'control' ] === 'field' && $option[ 'type' ] === 'array' ),
				'children' => $this->buildItems( $layoutItem[ 'children' ] ?? [], $displayOptions ),
				'is_on'    => $this->isOn( $control, $option ),
				'issues'   => $this->issuesByOption[ $layoutItem[ 'key' ] ] ?? [],
			];

			switch ( $control ) {
				case 'slider':
					$item = \array_merge( $item, $this->sliderData( $option, $layoutItem[ 'stops' ] ?? [], (bool)( $layoutItem[ 'zero_disables' ] ?? false ) ) );
					break;
				case 'switch':
					$item[ 'on_value' ] = (string)\current( \array_diff( \array_keys( $option[ 'value_options' ] ), [ 'disabled' ] ) );
					break;
				case 'roles':
					$item[ 'roles' ] = $this->roleChoices( $layoutItem[ 'key' ] );
					break;
				default:
					break;
			}

			$items[] = $item;
		}
		return $items;
	}

	/**
	 * A segmented control only reads well on one line within the control column (about 330px), so
	 * choices that would wrap are shown as a list instead.
	 */
	private function segmentsFitOneLine( array $option ) :bool {
		$width = 0;
		foreach ( $option[ 'value_options' ] as $choice ) {
			// Roughly 7px a character at the control's size, plus each segment's padding and border.
			$width += \strlen( \strip_tags( \html_entity_decode( (string)$choice[ 'name' ] ) ) ) * 7 + 26;
		}
		return $width <= 330;
	}

	/**
	 * Whether a switch is on, so the settings beneath it can show as not yet in effect.
	 */
	private function isOn( string $control, array $option ) :bool {
		if ( $control === 'toggle' ) {
			return $option[ 'value' ] === 'Y';
		}
		if ( $control === 'switch' ) {
			return $option[ 'value' ] !== 'disabled';
		}
		if ( $control === 'slider' && $option[ 'type' ] === 'integer' ) {
			return (int)$option[ 'value' ] > 0;
		}
		return true;
	}

	/**
	 * A select's choices in spec order, or an integer's chosen stops with the current value added if it
	 * isn't one of them, so saving never changes a value the user didn't touch. Where 0 turns the setting
	 * off rather than meaning nothing at all, its stop says so.
	 * @return array{stops:list<array{value:string,label:string,is_available:bool}>,current_index:int,current_label:string}
	 */
	private function sliderData( array $option, array $integerStops, bool $zeroDisables = false ) :array {
		$stops = [];
		if ( $option[ 'type' ] === 'select' ) {
			foreach ( $option[ 'value_options' ] as $value => $choice ) {
				$stops[] = [
					'value'        => (string)$value,
					'label'        => \strip_tags( \html_entity_decode( (string)$choice[ 'name' ] ) ),
					'is_available' => (bool)$choice[ 'is_available' ],
				];
			}
		}
		else {
			$values = \array_map( 'intval', $integerStops );
			$current = (int)$option[ 'value' ];
			if ( !\in_array( $current, $values, true ) ) {
				$values[] = $current;
				\sort( $values );
			}
			$unit = (string)( self::con()->cfg->configuration->options[ $option[ 'key' ] ][ 'unit' ] ?? '' );
			foreach ( $values as $value ) {
				$stops[] = [
					'value'        => (string)$value,
					'label'        => $value === 0 && $zeroDisables
						? __( '0 (disabled)', 'wp-simple-firewall' )
						: $this->integerLabel( $value, $unit ),
					'is_available' => true,
				];
			}
		}

		$currentIndex = 0;
		foreach ( $stops as $index => $stop ) {
			if ( $stop[ 'value' ] === (string)$option[ 'value' ] ) {
				$currentIndex = $index;
			}
		}

		return [
			'stops'         => $stops,
			'current_index' => $currentIndex,
			'current_label' => $stops[ $currentIndex ][ 'label' ] ?? '',
		];
	}

	private function integerLabel( int $value, string $unit ) :string {
		if ( $value === 0 ) {
			return __( 'Off', 'wp-simple-firewall' );
		}
		$units = [
			'seconds'  => _n( '%s second', '%s seconds', $value, 'wp-simple-firewall' ),
			'minutes'  => _n( '%s minute', '%s minutes', $value, 'wp-simple-firewall' ),
			'hours'    => _n( '%s hour', '%s hours', $value, 'wp-simple-firewall' ),
			'days'     => _n( '%s day', '%s days', $value, 'wp-simple-firewall' ),
			'comments' => _n( '%s comment', '%s comments', $value, 'wp-simple-firewall' ),
			'offenses' => _n( '%s offense', '%s offenses', $value, 'wp-simple-firewall' ),
			'requests' => _n( '%s request', '%s requests', $value, 'wp-simple-firewall' ),
		];
		return sprintf( $units[ $unit ] ?? '%s', number_format_i18n( $value ) );
	}

	/**
	 * Editable WordPress roles, plus any saved role no longer registered so saving keeps it.
	 * @return list<array{value:string,label:string,is_on:bool}>
	 */
	private function roleChoices( string $optionKey ) :array {
		$selected = \array_map( 'strval', (array)self::con()->opts->optGet( $optionKey ) );
		$names = \function_exists( 'wp_roles' ) ? wp_roles()->get_names() : [];
		$choices = [];
		foreach ( $names as $role => $name ) {
			$choices[] = [
				'value' => (string)$role,
				'label' => translate_user_role( $name ),
				'is_on' => \in_array( (string)$role, $selected, true ),
			];
		}
		foreach ( \array_diff( $selected, \array_keys( $names ) ) as $role ) {
			$choices[] = [
				'value' => $role,
				'label' => $role,
				'is_on' => true,
			];
		}
		return $choices;
	}
}
