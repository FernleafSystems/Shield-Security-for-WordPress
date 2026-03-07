<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

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
	 *   action:string
	 * }>
	 */
	public function buildQueueItems() :array {
		return \array_values( \array_filter( [
			$this->buildWordPressUpdateItem(),
			$this->buildPluginUpdatesItem(),
			$this->buildThemeUpdatesItem(),
		] ) );
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
	 *   action:string
	 * }|null
	 */
	private function buildWordPressUpdateItem() :?array {
		return Services::WpGeneral()->hasCoreUpdate()
			? $this->buildItem(
				'wp_updates',
				__( 'WordPress Version', 'wp-simple-firewall' ),
				1,
				'warning',
				__( 'There is an upgrade available for WordPress.', 'wp-simple-firewall' ),
				Services::WpGeneral()->getAdminUrl_Updates(),
				__( 'Update', 'wp-simple-firewall' )
			)
			: null;
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
	 *   action:string
	 * }|null
	 */
	private function buildPluginUpdatesItem() :?array {
		$count = \count( Services::WpPlugins()->getUpdates() );
		return $this->buildItem(
			'wp_plugins_updates',
			__( 'Plugins With Updates', 'wp-simple-firewall' ),
			$count,
			'warning',
			$count === 1
				? __( 'There is 1 plugin update waiting to be applied.', 'wp-simple-firewall' )
				: \sprintf( __( 'There are %s plugin updates waiting to be applied.', 'wp-simple-firewall' ), $count ),
			URL::Build( Services::WpGeneral()->getAdminUrl_Plugins( true ), [
				'plugin_status' => 'upgrade',
			] ),
			__( 'Update', 'wp-simple-firewall' )
		);
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
	 *   action:string
	 * }|null
	 */
	private function buildThemeUpdatesItem() :?array {
		$count = \count( Services::WpThemes()->getUpdates() );
		return $this->buildItem(
			'wp_themes_updates',
			__( 'Themes With Updates', 'wp-simple-firewall' ),
			$count,
			'warning',
			$count === 1
				? __( 'There is 1 theme update waiting to be applied.', 'wp-simple-firewall' )
				: \sprintf( __( 'There are %s theme updates waiting to be applied.', 'wp-simple-firewall' ), $count ),
			Services::WpGeneral()->getAdminUrl_Themes( true ),
			__( 'Update', 'wp-simple-firewall' )
		);
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
	 *   action:string
	 * }|null
	 */
	private function buildItem(
		string $key,
		string $label,
		int $count,
		string $severity,
		string $text,
		string $href,
		string $action
	) :?array {
		return $count > 0
			? [
				'key'      => $key,
				'zone'     => 'maintenance',
				'label'    => $label,
				'count'    => $count,
				'severity' => $severity,
				'text'     => $text,
				'href'     => $href,
				'action'   => $action,
			]
			: null;
	}
}
