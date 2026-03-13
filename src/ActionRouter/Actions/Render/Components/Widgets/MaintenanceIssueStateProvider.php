<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\Base as MeterComponentBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class MaintenanceIssueStateProvider {

	use PluginControllerConsumer;

	public const OPT_KEY = 'ignored_maintenance_items';
	public const SINGLETON_TOKEN = '__self__';

	/**
	 * @return array<string,array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   count:int,
	 *   ignored_count:int,
	 *   severity:string,
	 *   href:string,
	 *   action:string,
	 *   target:string,
	 *   supports_sub_items:bool,
	 *   active_identifiers:list<string>,
	 *   ignored_identifiers:list<string>
	 * }>
	 */
	public function buildStates() :array {
		$states = [];
		$contexts = $this->buildContexts();
		$ignoredByKey = $this->normalizeIgnoredItems(
			$this->getStoredIgnoredItems(),
			$this->buildValidIdentifiersByKey( $contexts )
		);

		foreach ( $contexts as $context ) {
			$states[ $context[ 'key' ] ] = $this->buildStateFromContext(
				$context,
				$ignoredByKey[ $context[ 'key' ] ]
			);
		}

		return $states;
	}

	/**
	 * @param list<array{
	 *   key:string,
	 *   component:array<string,mixed>,
	 *   issue_identifiers:list<string>,
	 *   supports_sub_items:bool
	 * }> $contexts
	 * @return array<string,list<string>>
	 */
	private function buildValidIdentifiersByKey( array $contexts ) :array {
		$validIdentifiersByKey = [];
		foreach ( $this->maintenanceKeys() as $key ) {
			$validIdentifiersByKey[ $key ] = [];
		}
		foreach ( $contexts as $context ) {
			$validIdentifiersByKey[ $context[ 'key' ] ] = $context[ 'issue_identifiers' ];
		}
		return $validIdentifiersByKey;
	}

	/**
	 * @return array<string,list<string>>
	 */
	public function currentIssueIdentifiersByKey() :array {
		$identifiers = [];
		foreach ( $this->maintenanceKeys() as $key ) {
			$identifiers[ $key ] = [];
		}

		foreach ( $this->buildContexts() as $context ) {
			$identifiers[ $context[ 'key' ] ] = $context[ 'issue_identifiers' ];
		}

		return $identifiers;
	}

	public function isKnownMaintenanceKey( string $key ) :bool {
		return \in_array( $key, $this->maintenanceKeys(), true );
	}

	public function supportsSubItems( string $key ) :bool {
		return \in_array( $key, [
			'wp_plugins_updates',
			'wp_themes_updates',
			'wp_plugins_inactive',
			'wp_themes_inactive',
		], true );
	}

	/**
	 * @param array<string,mixed> $ignoredItems
	 * @param array<string,list<string>>|null $validIdentifiersByKey
	 * @return array<string,list<string>>
	 */
	public function normalizeIgnoredItems( array $ignoredItems, ?array $validIdentifiersByKey = null ) :array {
		$normalized = $this->defaultIgnoredItems();

		foreach ( $normalized as $key => $default ) {
			$values = \is_array( $ignoredItems[ $key ] ?? null ) ? $ignoredItems[ $key ] : [];
			$values = \array_values( \array_filter( \array_map(
				static fn( $value ) :string => \is_scalar( $value ) ? \trim( (string)$value ) : '',
				$values
			) ) );
			$values = \array_values( \array_unique( $values ) );

			if ( $validIdentifiersByKey !== null ) {
				$validIdentifiers = $validIdentifiersByKey[ $key ] ?? [];
				$values = \array_values( \array_intersect( $values, $validIdentifiers ) );
			}

			\natsort( $values );
			$normalized[ $key ] = \array_values( $values );
		}

		return $normalized;
	}

	/**
	 * @return array<string,list<string>>
	 */
	public function normalizeCurrentIgnoredItems() :array {
		return $this->normalizeIgnoredItems(
			$this->getStoredIgnoredItems(),
			$this->currentIssueIdentifiersByKey()
		);
	}

	/**
	 * @return array<string,list<string>>
	 */
	public function defaultIgnoredItems() :array {
		return \array_fill_keys( $this->maintenanceKeys(), [] );
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   component:array<string,mixed>,
	 *   issue_identifiers:list<string>,
	 *   supports_sub_items:bool
	 * }>
	 */
	protected function buildContexts() :array {
		$contexts = [];

		foreach ( $this->getDefinitions() as $definition ) {
			$context = $this->buildContext( $definition );
			if ( $context !== null ) {
				$contexts[] = $context;
			}
		}

		return $contexts;
	}

	/**
	 * @param array{
	 *   key:string,
	 *   zone:string,
	 *   component_class:class-string<MeterComponentBase>,
	 *   availability_strategy:string
	 * } $definition
	 * @return array{
	 *   key:string,
	 *   component:array<string,mixed>,
	 *   issue_identifiers:list<string>,
	 *   supports_sub_items:bool
	 * }|null
	 */
	protected function buildContext( array $definition ) :?array {
		$component = $this->buildComponent( $definition[ 'component_class' ] );
		if ( !$component[ 'is_applicable' ] ) {
			return null;
		}

		$key = $definition[ 'key' ];
		return [
			'key'                => $key,
			'component'          => $component,
			'issue_identifiers'  => $this->issueIdentifiersForKey( $key, $component ),
			'supports_sub_items' => $this->supportsSubItems( $key ),
		];
	}

	/**
	 * @param array{
	 *   key:string,
	 *   component:array<string,mixed>,
	 *   issue_identifiers:list<string>,
	 *   supports_sub_items:bool
	 * } $context
	 * @param list<string> $ignoredIdentifiers
	 * @return array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   count:int,
	 *   ignored_count:int,
	 *   severity:string,
	 *   href:string,
	 *   action:string,
	 *   target:string,
	 *   supports_sub_items:bool,
	 *   active_identifiers:list<string>,
	 *   ignored_identifiers:list<string>
	 * }
	 */
	protected function buildStateFromContext( array $context, array $ignoredIdentifiers ) :array {
		$component = $context[ 'component' ];
		$activeIdentifiers = \array_values( \array_diff( $context[ 'issue_identifiers' ], $ignoredIdentifiers ) );
		$activeCount = \count( $activeIdentifiers );
		$ignoredCount = \count( $ignoredIdentifiers );
		$severity = $component[ 'is_critical' ] ? 'critical' : 'warning';

		if ( $activeCount > 0 ) {
			$description = $this->buildActiveDescription(
				$context[ 'key' ],
				$activeCount,
				$ignoredCount,
				$component
			);
		}
		elseif ( $ignoredCount > 0 ) {
			$severity = 'good';
			$description = $this->buildIgnoredDescription(
				$ignoredCount,
				$context[ 'supports_sub_items' ]
			);
		}
		else {
			$severity = 'good';
			$description = $component[ 'desc_protected' ];
		}

		$action = \trim( $component[ 'fix' ] );

		return [
			'key'                 => $context[ 'key' ],
			'label'               => $component[ 'title' ],
			'description'         => $description,
			'count'               => $activeCount,
			'ignored_count'       => $ignoredCount,
			'severity'            => $severity,
			'href'                => $component[ 'href_full' ],
			'action'             => $action === '' ? __( 'Fix', 'wp-simple-firewall' ) : $action,
			'target'              => $component[ 'href_full_target_blank' ] ? '_blank' : '',
			'supports_sub_items' => $context[ 'supports_sub_items' ],
			'active_identifiers'  => $activeIdentifiers,
			'ignored_identifiers' => $ignoredIdentifiers,
		];
	}

	/**
	 * @param class-string<MeterComponentBase> $componentClass
	 * @return array<string,mixed>
	 */
	protected function buildComponent( string $componentClass ) :array {
		return ( new $componentClass() )->build( MeterComponentBase::CHANNEL_ACTION );
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   zone:string,
	 *   component_class:class-string<MeterComponentBase>,
	 *   availability_strategy:string
	 * }>
	 */
	protected function getDefinitions() :array {
		return \array_values( \array_filter(
			PluginNavs::actionsLandingAssessmentDefinitions(),
			static fn( array $definition ) :bool => $definition[ 'zone' ] === 'maintenance'
		) );
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function getStoredIgnoredItems() :array {
		$stored = self::con()->opts->optGet( self::OPT_KEY );
		return \is_array( $stored ) ? $stored : [];
	}

	/**
	 * @param array<string,mixed> $component
	 * @return list<string>
	 */
	protected function issueIdentifiersForKey( string $key, array $component ) :array {
		if ( $component[ 'is_protected' ] ) {
			return [];
		}

		switch ( $key ) {
			case 'wp_plugins_updates':
				return \array_values( \array_keys( Services::WpPlugins()->getUpdates() ) );

			case 'wp_themes_updates':
				return \array_values( \array_keys( Services::WpThemes()->getUpdates() ) );

			case 'wp_plugins_inactive':
				return \array_values( \array_diff(
					\array_keys( Services::WpPlugins()->getPlugins() ),
					Services::WpPlugins()->getActivePlugins()
				) );

			case 'wp_themes_inactive':
				return \array_values( \array_diff(
					\array_keys( Services::WpThemes()->getThemes() ),
					$this->activeThemeStylesheets()
				) );

			default:
				return [ self::SINGLETON_TOKEN ];
		}
	}

	/**
	 * @return list<string>
	 */
	private function maintenanceKeys() :array {
		return \array_map(
			static fn( array $definition ) :string => $definition[ 'key' ],
			$this->getDefinitions()
		);
	}

	/**
	 * @param array<string,mixed> $component
	 */
	private function buildActiveDescription( string $key, int $activeCount, int $ignoredCount, array $component ) :string {
		switch ( $key ) {
			case 'wp_plugins_updates':
				$description = \sprintf(
					_n(
						'There is 1 plugin update waiting to be applied.',
						'There are %s plugin updates waiting to be applied.',
						$activeCount,
						'wp-simple-firewall'
					),
					$activeCount
				);
				break;

			case 'wp_themes_updates':
				$description = \sprintf(
					_n(
						'There is 1 theme update waiting to be applied.',
						'There are %s theme updates waiting to be applied.',
						$activeCount,
						'wp-simple-firewall'
					),
					$activeCount
				);
				break;

			case 'wp_plugins_inactive':
				$description = \sprintf(
					_n(
						'There is 1 unused plugin that should be uninstalled.',
						'There are %s unused plugins that should be uninstalled.',
						$activeCount,
						'wp-simple-firewall'
					),
					$activeCount
				);
				break;

			case 'wp_themes_inactive':
				$description = \sprintf(
					_n(
						'There is 1 unused theme that should be uninstalled.',
						'There are %s unused themes that should be uninstalled.',
						$activeCount,
						'wp-simple-firewall'
					),
					$activeCount
				);
				break;

			default:
				$description = $component[ 'desc_unprotected' ];
				break;
		}

		if ( $ignoredCount > 0 ) {
			$description = \trim( $description.' '.$this->buildIgnoredDescription( $ignoredCount, true ) );
		}

		return $description;
	}

	private function buildIgnoredDescription( int $ignoredCount, bool $supportsSubItems ) :string {
		return $supportsSubItems
			? \sprintf(
				_n(
					'%s item is currently ignored.',
					'%s items are currently ignored.',
					$ignoredCount,
					'wp-simple-firewall'
				),
				$ignoredCount
			)
			: __( 'This maintenance item is currently ignored.', 'wp-simple-firewall' );
	}

	/**
	 * @return list<string>
	 */
	private function activeThemeStylesheets() :array {
		$stylesheets = [];
		$current = Services::WpThemes()->getCurrent();
		if ( \is_object( $current ) && \method_exists( $current, 'get_stylesheet' ) ) {
			$stylesheets[] = (string)$current->get_stylesheet();
		}

		$currentParent = Services::WpThemes()->getCurrentParent();
		if ( \is_object( $currentParent ) && \method_exists( $currentParent, 'get_stylesheet' ) ) {
			$stylesheets[] = (string)$currentParent->get_stylesheet();
		}

		return \array_values( \array_unique( \array_filter( $stylesheets ) ) );
	}
}
