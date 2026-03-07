<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\ZoneComponentConfig;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

abstract class Base extends \FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\Base {

	protected function run() {
	}

	public function title() :string {
		return __( 'No Name Yet', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'No Subtitle Yet', 'wp-simple-firewall' );
	}

	protected function tooltip() :string {
		return '';
	}

	public function explanation() :array {
		return $this->status()[ 'exp' ];
	}

	public function enabledStatus() :string {
		return $this->status()[ 'level' ];
	}

	protected function hasCapability() :bool {
		return true;
	}

	public function getActions() :array {
		$actions = [];
		if ( $this->hasConfigAction() ) {
			$actions[ 'config' ] = [
				'title'   => __( 'Edit Settings', 'wp-simple-firewall' ),
				'data'    => \array_merge(
					[
						'zone_component_action' => ZoneComponentConfig::SLUG,
						'zone_component_slug'   => static::Slug(),
					],
					empty( $this->tooltip() ) ? [] : [
						'bs-toggle'    => 'tooltip',
						'bs-trigger'   => 'hover',
						'bs-placement' => 'right',
						'bs-title'     => $this->tooltip(),
					]
				),
				'icon'    => self::con()->svgs->iconClass( 'gear' ),
				'classes' => [
					'zone_component_action',
				],
			];
		}
		return $actions;
	}

	public function getOptions() :array {
		return \array_keys( \array_filter(
			self::con()->cfg->configuration->options,
			fn( array $option ) => \in_array( static::Slug(), $option[ 'zone_comp_slugs' ] ?? [] )
		) );
	}

	public function getLinks() :array {
		return [];
	}

	/**
	 * @return array<int,array{
	 *   slug:string,
	 *   title:string,
	 *   weight:int,
	 *   score:int,
	 *   is_protected:bool,
	 *   severity:string,
	 *   explanation:list<string>,
	 *   config_action:array<string,mixed>
	 * }>
	 */
	public function postureSignals() :array {
		$weight = $this->postureWeight();
		if ( $weight <= 0 ) {
			return [];
		}

		$severity = EnumEnabledStatus::toSeverity( $this->enabledStatus(), 'good' );
		return [
			$this->buildPostureSignal(
				static::Slug(),
				$this->title(),
				$weight,
				$this->scoreForSeverity( $severity, $weight ),
				$severity,
				$severity === 'good',
				$this->explanation()
			),
		];
	}

	protected function hasConfigAction() :bool {
		return true;
	}

	/**
	 * @return array{level:string,expl:string[]}
	 */
	protected function status() :array {
		return [
			'level' => EnumEnabledStatus::NEUTRAL,
			'exp'   => [],
		];
	}

	protected function postureWeight() :int {
		return 0;
	}

	/**
	 * @param string[] $explanation
	 * @return array{
	 *   slug:string,
	 *   title:string,
	 *   weight:int,
	 *   score:int,
	 *   is_protected:bool,
	 *   severity:string,
	 *   explanation:list<string>,
	 *   config_action:array<string,mixed>
	 * }
	 */
	protected function buildPostureSignal(
		string $slug,
		string $title,
		int $weight,
		int $score,
		string $severity,
		bool $isProtected,
		array $explanation = []
	) :array {
		return [
			'slug'         => sanitize_key( $slug ),
			'title'        => $title,
			'weight'       => max( 0, $weight ),
			'score'        => max( 0, min( $weight, $score ) ),
			'is_protected' => $isProtected,
			'severity'     => \in_array( $severity, [ 'good', 'warning', 'critical' ], true ) ? $severity : 'good',
			'explanation'  => \array_values( \array_filter( \array_map(
				static fn( $line ) :string => trim( (string)$line ),
				$explanation
			) ) ),
			'config_action' => $this->getActions()[ 'config' ] ?? [],
		];
	}

	protected function scoreForSeverity( string $severity, int $weight ) :int {
		switch ( $severity ) {
			case 'critical':
				return 0;
			case 'warning':
				return (int)round( $weight/2 );
			case 'good':
			default:
				return $weight;
		}
	}
}
