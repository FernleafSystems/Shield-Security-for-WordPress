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
			$configData = [
				'zone_component_action' => ZoneComponentConfig::SLUG,
				'zone_component_slug'   => \implode( ',', $this->configZoneComponentSlugs() ),
			];
			$configItem = $this->configItem();
			if ( $configItem !== '' ) {
				$configData[ 'config_item' ] = $configItem;
			}

			$actions[ 'config' ] = [
				'title'   => __( 'Edit Settings', 'wp-simple-firewall' ),
				'data'    => \array_merge(
					$configData,
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
		$ownerSlugs = $this->configZoneComponentSlugs();
		return \array_keys( \array_filter(
			self::con()->cfg->configuration->options,
			static fn( array $option ) :bool => \count( \array_intersect(
				$ownerSlugs,
				\array_filter( $option[ 'zone_comp_slugs' ] ?? [], 'is_string' )
			) ) > 0
		) );
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   title:string,
	 *   enabled_status:string,
	 *   note:string,
	 *   explanations:list<string>,
	 *   config_scope:array{
	 *     zone_component_slugs:list<string>,
	 *     option_keys:list<string>,
	 *     config_item:string,
	 *     title:string
	 *   }
	 * }>
	 */
	public function configureRows() :array {
		$configureStatus = $this->configureStatus();
		return [
			$this->buildConfigureRowInput(
				$this->configureRowKey(),
				$this->title(),
				$configureStatus[ 'level' ],
				$this->configureRowNote( $configureStatus[ 'exp' ] ),
				$configureStatus[ 'exp' ],
				[
					'zone_component_slugs' => $this->configZoneComponentSlugs(),
					'option_keys'          => $this->getOptions(),
					'config_item'          => $this->configItem(),
					'title'                => $this->configureActionTitle(),
				]
			),
		];
	}

	public function configureRowKey() :string {
		foreach ( $this->configZoneComponentSlugs() as $slug ) {
			$key = sanitize_key( \is_string( $slug ) ? \trim( $slug ) : '' );
			if ( $key !== '' ) {
				return $key;
			}
		}

		throw new \LogicException( 'Configure rows require a stable non-empty row scope slug.' );
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
		return \count( $this->getOptions() ) > 0;
	}

	/**
	 * @param list<string> $explanations
	 */
	protected function configureRowNote( array $explanations = [] ) :string {
		$subtitle = \trim( $this->subtitle() );
		if ( $subtitle !== '' ) {
			return $subtitle;
		}

		$firstExplanation = \trim( (string)( ( $explanations[ 0 ] ?? $this->explanation()[ 0 ] ?? '' ) ) );
		return $firstExplanation;
	}

	protected function configureActionTitle() :string {
		return __( 'Edit Settings', 'wp-simple-firewall' );
	}

	/**
	 * @return array{level:string,exp:list<string>}
	 */
	protected function configureStatus() :array {
		$status = [
			'level' => $this->enabledStatus(),
			'exp'   => $this->explanation(),
		];

		if ( $status[ 'level' ] === EnumEnabledStatus::BAD
			 && $this->configureTreatsBadStatusAsWarning() ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
		}

		return $status;
	}

	protected function configureTreatsBadStatusAsWarning() :bool {
		return false;
	}

	/**
	 * @return list<string>
	 */
	protected function configZoneComponentSlugs() :array {
		$slugs = \array_values( \array_unique( \array_filter(
			[ static::Slug() ],
			static fn( $slug ) :bool => \is_string( $slug ) && $slug !== ''
		) ) );
		return empty( $slugs ) ? [ static::Slug() ] : $slugs;
	}

	protected function configItem() :string {
		return '';
	}

	/**
	 * @param list<string> $sections
	 * @return list<string>
	 */
	protected function configureRowOptionsForSections( array $sections ) :array {
		$sections = \array_values( \array_filter( \array_map(
			static fn( $section ) :string => \trim( (string)$section ),
			$sections
		) ) );
		if ( empty( $sections ) ) {
			return [];
		}

		return \array_values( \array_filter(
			$this->getOptions(),
			static fn( string $optionKey ) :bool => \in_array(
				(string)( self::con()->cfg->configuration->options[ $optionKey ][ 'section' ] ?? '' ),
				$sections,
				true
			)
		) );
	}

	/**
	 * @param list<string> $zoneComponentSlugs
	 * @param list<string> $optionKeys
	 * @return array{
	 *   zone_component_slugs:list<string>,
	 *   option_keys:list<string>,
	 *   config_item:string,
	 *   title:string
	 * }
	 */
	protected function buildConfigureRowScope(
		array $zoneComponentSlugs,
		array $optionKeys,
		string $configItem = '',
		string $title = ''
	) :array {
		$zoneComponentSlugs = \array_values( \array_filter( \array_map(
			static fn( $slug ) :string => \trim( (string)$slug ),
			$zoneComponentSlugs
		) ) );
		$optionKeys = \array_values( \array_filter( \array_map(
			static fn( $optionKey ) :string => \trim( (string)$optionKey ),
			$optionKeys
		) ) );

		return [
			'zone_component_slugs' => $zoneComponentSlugs,
			'option_keys'          => $optionKeys,
			'config_item'          => \trim( $configItem ),
			'title'                => \trim( $title ),
		];
	}

	/**
	 * @param list<string> $explanations
	 * @return array{
	 *   key:string,
	 *   title:string,
	 *   enabled_status:string,
	 *   note:string,
	 *   explanations:list<string>,
	 *   config_scope:array{
	 *     zone_component_slugs:list<string>,
	 *     option_keys:list<string>,
	 *     config_item:string,
	 *     title:string
	 *   }
	 * }
	 */
	protected function buildConfigureRowInput(
		string $key,
		string $title,
		string $enabledStatus,
		string $note,
		array $explanations,
		array $configScope
	) :array {
		$key = sanitize_key( $key );
		if ( $key === '' ) {
			throw new \LogicException( 'Configure rows require a stable non-empty producer-owned key.' );
		}

		return [
			'key'            => $key,
			'title'          => \trim( $title ),
			'enabled_status' => \trim( $enabledStatus ),
			'note'           => \trim( $note ),
			'explanations'   => \array_values( \array_filter( \array_map(
				static fn( $line ) :string => \trim( (string)$line ),
				$explanations
			) ) ),
			'config_scope'   => $this->buildConfigureRowScope(
				$configScope[ 'zone_component_slugs' ] ?? [],
				$configScope[ 'option_keys' ] ?? [],
				(string)( $configScope[ 'config_item' ] ?? '' ),
				(string)( $configScope[ 'title' ] ?? '' )
			),
		];
	}

	/**
	 * @return array{level:string,exp:string[]}
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

	protected function resolveBooleanMethod( object $object, string $method, bool $fallback ) :bool {
		return \method_exists( $object, $method ) ? (bool)$object->{$method}() : $fallback;
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
