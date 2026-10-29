<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureGoalPanels;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * Turns a topic's panel layout into render data: each item gets its option's display data (name, summary,
 * value, choices, Pro availability) plus what its control needs, such as a slider's stops.
 */
class ConfigurePanelBuilder {

	use PluginControllerConsumer;

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
		$headings = ConfigureGoalPanels::labels()[ 'headings' ];

		$blocks = [];
		foreach ( $topic[ 'blocks' ] as $block ) {
			$items = $this->buildItems( $block[ 'items' ], $displayOptions );
			if ( !empty( $items ) ) {
				$blocks[] = [
					'heading' => $headings[ $block[ 'heading' ] ?? '' ] ?? '',
					'items'   => $items,
				];
			}
		}

		$more = $this->buildItems( $topic[ 'more' ] ?? [], $displayOptions );

		return [
			'goal'         => $goalKey,
			'topic'        => $topicKey,
			'blocks'       => $blocks,
			'more'         => $more,
			'more_summary' => \implode( ', ', \array_map( fn( array $item ) => $item[ 'option' ][ 'name' ], $more ) ),
		];
	}

	private function buildItems( array $layoutItems, array $displayOptions ) :array {
		$items = [];
		foreach ( $layoutItems as $layoutItem ) {
			$option = $displayOptions[ $layoutItem[ 'key' ] ] ?? null;
			if ( !\is_array( $option ) ) {
				continue;
			}
			$item = [
				'key'      => $layoutItem[ 'key' ],
				'control'  => $layoutItem[ 'control' ],
				'option'   => $option,
				'stacked'  => \in_array( $layoutItem[ 'control' ], [ 'slider', 'chips', 'roles', 'radios' ], true )
							  || ( $layoutItem[ 'control' ] === 'field' && \in_array( $option[ 'type' ], [ 'array', 'password' ], true ) ),
				'children' => $this->buildItems( $layoutItem[ 'children' ] ?? [], $displayOptions ),
				'is_on'    => $this->isOn( $layoutItem[ 'control' ], $option ),
			];

			switch ( $layoutItem[ 'control' ] ) {
				case 'slider':
					$item = \array_merge( $item, $this->sliderData( $option, $layoutItem[ 'stops' ] ?? [] ) );
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
	 * isn't one of them, so saving never changes a value the user didn't touch.
	 * @return array{stops:list<array{value:string,label:string,is_available:bool}>,current_index:int,current_label:string}
	 */
	private function sliderData( array $option, array $integerStops ) :array {
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
					'label'        => $this->integerLabel( $value, $unit ),
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
