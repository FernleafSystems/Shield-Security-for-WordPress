<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureGoalPanels;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Every option a user can see must have a place in a Configure panel, or on one of the pages that manage
 * their own options. Adding an option to the spec without placing it fails here.
 */
class ConfigureGoalPanelsCoverageTest extends TestCase {

	use PluginPathsTrait;

	private const CONTROLS_BY_TYPE = [
		'toggle'    => [ 'checkbox' ],
		'switch'    => [ 'select' ],
		'slider'    => [ 'select', 'integer' ],
		'segmented' => [ 'select' ],
		'radios'    => [ 'select' ],
		'chips'     => [ 'multiple_select' ],
		'roles'     => [ 'array' ],
		'field'     => [ 'text', 'email', 'password', 'select', 'array', 'integer', 'noneditable_text' ],
	];

	public function test_every_visible_option_is_placed_exactly_once() :void {
		$placed = $this->placedOptionKeys();
		$duplicates = \array_keys( \array_filter( \array_count_values( $placed ), static fn( int $n ) :bool => $n > 1 ) );
		$this->assertSame( [], $duplicates, 'Options placed more than once' );

		$missing = \array_values( \array_diff( \array_keys( $this->visibleOptions() ), $placed ) );
		$this->assertSame( [], $missing, 'Visible options with no Configure panel or own page. Add each one to ConfigureGoalPanels::goals() or ::ownPageOptions().' );
	}

	public function test_panels_only_reference_visible_options() :void {
		$unknown = \array_values( \array_diff( $this->placedOptionKeys(), \array_keys( $this->visibleOptions() ) ) );
		$this->assertSame( [], $unknown, 'Panels reference options that are unknown or hidden' );
	}

	public function test_each_control_suits_its_option_type() :void {
		$options = $this->visibleOptions();
		$mismatches = [];
		foreach ( $this->panelItems() as $item ) {
			$type = (string)( $options[ $item[ 'key' ] ][ 'type' ] ?? '' );
			if ( !\in_array( $type, self::CONTROLS_BY_TYPE[ $item[ 'control' ] ] ?? [], true ) ) {
				$mismatches[] = sprintf( '%s (%s) as %s', $item[ 'key' ], $type, $item[ 'control' ] );
			}
			if ( $item[ 'control' ] === 'switch' ) {
				$choices = \array_column( $options[ $item[ 'key' ] ][ 'value_options' ] ?? [], 'value_key' );
				if ( \count( $choices ) !== 2 || !\in_array( 'disabled', $choices, true ) ) {
					$mismatches[] = sprintf( '%s needs exactly two choices, one of them "disabled", to be a switch', $item[ 'key' ] );
				}
			}
			if ( $item[ 'control' ] === 'slider' && $type === 'integer' && empty( $item[ 'stops' ] ) ) {
				$mismatches[] = sprintf( '%s is an integer slider without stops', $item[ 'key' ] );
			}
		}
		$this->assertSame( [], $mismatches );
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function visibleOptions() :array {
		$config = $this->decodePluginJsonFile( 'plugin.json', 'Plugin configuration' );
		$visible = [];
		foreach ( $config[ 'config_spec' ][ 'options' ] ?? [] as $option ) {
			$section = (string)( $option[ 'section' ] ?? '' );
			if ( isset( $option[ 'key' ] ) && $section !== '' && $section !== 'section_hidden' ) {
				$visible[ $option[ 'key' ] ] = $option;
			}
		}
		$this->assertNotEmpty( $visible );
		return $visible;
	}

	/**
	 * @return list<string>
	 */
	private function placedOptionKeys() :array {
		$keys = \array_column( $this->panelItems(), 'key' );
		foreach ( ConfigureGoalPanels::ownPageOptions() as $ownPageKeys ) {
			$keys = \array_merge( $keys, $ownPageKeys );
		}
		return $keys;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function panelItems() :array {
		$items = [];
		foreach ( ConfigureGoalPanels::goals() as $goal ) {
			foreach ( $goal[ 'topics' ] as $topic ) {
				foreach ( $topic[ 'blocks' ] as $block ) {
					$items = \array_merge( $items, $this->flatten( $block[ 'items' ] ) );
				}
				$items = \array_merge( $items, $this->flatten( $topic[ 'more' ] ?? [] ) );
			}
		}
		return $items;
	}

	private function flatten( array $items ) :array {
		$flat = [];
		foreach ( $items as $item ) {
			$flat[] = $item;
			$flat = \array_merge( $flat, $this->flatten( $item[ 'children' ] ?? [] ) );
		}
		return $flat;
	}
}
