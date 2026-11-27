<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

abstract class Base extends \FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\Base {

	/**
	 * Explanation text => the option key that resolves it, so Configure can link straight to the setting.
	 * Static because component instances are created afresh by each consumer.
	 * @var array<string,string>
	 */
	private static array $explanationOptionKeys = [];

	protected function run() {
	}

	/**
	 * Records which option fixes an explanation, and returns the explanation so it can be added inline.
	 */
	protected function explanationFixedBy( string $explanation, string $optionKey ) :string {
		self::$explanationOptionKeys[ \trim( $explanation ) ] = $optionKey;
		return $explanation;
	}

	/**
	 * @return array<string,string> explanation text => option key, for explanations built so far in this request
	 */
	public static function explanationFixLookup() :array {
		return self::$explanationOptionKeys;
	}

	public function title() :string {
		return __( 'No Name Yet', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'No Subtitle Yet', 'wp-simple-firewall' );
	}

	public function explanation() :array {
		return $this->status()[ 'exp' ];
	}

	public function enabledStatus() :string {
		return $this->status()[ 'level' ];
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
			static fn( $slug ) :bool => $slug !== ''
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

		$scope = $this->buildConfigureRowScope(
			$configScope[ 'zone_component_slugs' ] ?? [],
			$configScope[ 'option_keys' ] ?? [],
			(string)( $configScope[ 'config_item' ] ?? '' ),
			(string)( $configScope[ 'title' ] ?? '' )
		);
		[ $enabledStatus, $explanations ] = $this->statusThisPlanCanActOn(
			\trim( $enabledStatus ),
			\array_values( \array_filter( \array_map(
				static fn( $line ) :string => \trim( (string)$line ),
				$explanations
			) ) ),
			$scope[ 'option_keys' ]
		);

		return [
			'key'            => $key,
			'title'          => \trim( $title ),
			'enabled_status' => $enabledStatus,
			'note'           => \trim( $note ),
			'explanations'   => $explanations,
			'config_scope'   => $scope,
		];
	}

	/**
	 * Configure only raises what the site can act on. A reason fixed by a setting outside the site's plan is left
	 * out, and a row that then has no reasons left no longer needs attention. A row made up only of such settings
	 * says nothing at all.
	 * @param list<string> $explanations
	 * @param list<string> $optionKeys
	 * @return array{0:string,1:list<string>}
	 */
	private function statusThisPlanCanActOn( string $status, array $explanations, array $optionKeys ) :array {
		$opts = self::con()->opts;
		$inPlan = static fn( string $optionKey ) :bool => $opts->optHasAccess( $optionKey );

		if ( !empty( $optionKeys ) && empty( \array_filter( $optionKeys, $inPlan ) ) ) {
			return [ EnumEnabledStatus::NEUTRAL, [] ];
		}

		$kept = \array_values( \array_filter(
			$explanations,
			static function ( string $explanation ) use ( $inPlan ) :bool {
				$fixedBy = self::$explanationOptionKeys[ $explanation ] ?? '';
				return $fixedBy === '' || $inPlan( $fixedBy );
			}
		) );
		if ( empty( $kept ) && !empty( $explanations )
			 && \in_array( $status, [ EnumEnabledStatus::BAD, EnumEnabledStatus::OKAY ], true ) ) {
			$status = EnumEnabledStatus::GOOD;
		}

		return [ $status, $kept ];
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

	protected function resolveBooleanMethod( object $object, string $method, bool $fallback ) :bool {
		return \method_exists( $object, $method ) ? (bool)$object->{$method}() : $fallback;
	}
}
