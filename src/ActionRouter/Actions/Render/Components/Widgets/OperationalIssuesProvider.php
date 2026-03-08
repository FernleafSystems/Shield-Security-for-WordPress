<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Services\Services;

class OperationalIssuesProvider {

	/**
	 * @return list<array{
	 *   key:string,
	 *   zone:string,
	 *   label:string,
	 *   count:int,
	 *   severity:string,
	 *   text:string,
	 *   href:string,
	 *   action:string,
	 *   target:string
	 * }>
	 */
	public function buildQueueItems() :array {
		$items = [];

		foreach ( $this->getDefinitions() as $definition ) {
			if ( ( $definition[ 'zone' ] ?? '' ) !== 'maintenance' ) {
				continue;
			}

			$item = $this->buildItemFromDefinition( $definition );
			if ( $item !== null ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   zone:string,
	 *   component_class:class-string<\FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\Base>,
	 *   availability_strategy:string
	 * }>
	 */
	protected function getDefinitions() :array {
		return PluginNavs::actionsLandingAssessmentDefinitions();
	}

	/**
	 * @return array{
	 *   key:string,
	 *   zone:string,
	 *   label:string,
	 *   count:int,
	 *   severity:string,
	 *   text:string,
	 *   href:string,
	 *   action:string,
	 *   target:string
	 * }|null
	 */
	protected function buildItemFromDefinition( array $definition ) :?array {
		$component = $this->buildComponent( $definition[ 'component_class' ] );
		if ( empty( $component[ 'is_applicable' ] ) || !empty( $component[ 'is_protected' ] ) ) {
			return null;
		}

		$count = $this->countForKey( (string)$definition[ 'key' ] );
		$action = \trim( (string)( $component[ 'fix' ] ?? '' ) );
		return $this->buildItem(
			(string)$definition[ 'key' ],
			(string)( $component[ 'title' ] ?? '' ),
			$count,
			!empty( $component[ 'is_critical' ] ) ? 'critical' : 'warning',
			(string)( $component[ 'desc_unprotected' ] ?? '' ),
			(string)( $component[ 'href_full' ] ?? '' ),
			$action === '' ? __( 'Fix', 'wp-simple-firewall' ) : $action,
			!empty( $component[ 'href_full_target_blank' ] ) ? '_blank' : ''
		);
	}

	/**
	 * @param class-string<\FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\Base> $componentClass
	 * @return array<string,mixed>
	 */
	protected function buildComponent( string $componentClass ) :array {
		return ( new $componentClass() )->build();
	}

	protected function countForKey( string $key ) :int {
		switch ( $key ) {
			case 'wp_updates':
				$count = Services::WpGeneral()->hasCoreUpdate() ? 1 : 0;
				break;
			case 'wp_plugins_updates':
				$count = \count( Services::WpPlugins()->getUpdates() );
				break;
			case 'wp_themes_updates':
				$count = \count( Services::WpThemes()->getUpdates() );
				break;
			case 'wp_plugins_inactive':
				$wpPlugins = Services::WpPlugins();
				$count = \count( $wpPlugins->getPlugins() ) - \count( $wpPlugins->getActivePlugins() );
				break;
			case 'wp_themes_inactive':
				$wpThemes = Services::WpThemes();
				$count = \count( $wpThemes->getThemes() ) - ( $wpThemes->isActiveThemeAChild() ? 2 : 1 );
				break;
			default:
				$count = 1;
				break;
		}

		return \max( 0, $count );
	}

	/**
	 * @return array{
	 *   key:string,
	 *   zone:string,
	 *   label:string,
	 *   count:int,
	 *   severity:string,
	 *   text:string,
	 *   href:string,
	 *   action:string,
	 *   target:string
	 * }|null
	 */
	private function buildItem(
		string $key,
		string $label,
		int $count,
		string $severity,
		string $text,
		string $href,
		string $action,
		string $target
	) :?array {
		return ( $count > 0 && $label !== '' && $text !== '' )
			? [
				'key'      => $key,
				'zone'     => 'maintenance',
				'label'    => $label,
				'count'    => $count,
				'severity' => $severity,
				'text'     => $text,
				'href'     => $href,
				'action'   => $action,
				'target'   => $target,
			]
			: null;
	}
}
