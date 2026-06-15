<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins\HiddenPluginFinding;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

/**
 * @phpstan-import-type HiddenPluginAlertData from HiddenPluginFinding
 */
class EmailInstantAlertHiddenPlugins extends EmailInstantAlertBase {

	public const SLUG = 'email_instant_alert_hidden_plugins';

	protected function getBodyData() :array {
		return Services::DataManipulation()->mergeArraysRecursive( parent::getBodyData(), [
			'hrefs'   => [
				'url_plugins' => Services::WpGeneral()->getAdminUrl( 'plugins.php' ),
			],
			'strings' => [
				'intro' => [
					__( 'A plugin file is present on disk but is not visible in the WordPress admin plugin list.', 'wp-simple-firewall' ),
				],
				'outro' => [
					__( 'Review the plugin file immediately if this change was not expected.', 'wp-simple-firewall' ),
				],
			],
		] );
	}

	protected function buildAlertGroups() :array {
		return [
			'hidden_plugins' => [
				'title' => __( 'Hidden Plugin Findings', 'wp-simple-firewall' ),
				'items' => \array_map(
					fn( array $finding ) :array => $this->buildFindingItem( $finding ),
					$this->hiddenPluginAlertData()
				),
			],
		];
	}

	/**
	 * @return list<HiddenPluginAlertData>
	 */
	private function hiddenPluginAlertData() :array {
		/** @var list<HiddenPluginAlertData> $hiddenPlugins */
		$hiddenPlugins = $this->action_data[ 'alert_data' ][ 'hidden_plugins' ];
		return $hiddenPlugins;
	}

	/**
	 * @param HiddenPluginAlertData $finding
	 */
	private function buildFindingItem( array $finding ) :array {
		$hiddenBy = \implode( ', ', $finding[ 'hidden_by_labels' ] );

		return [
			'text' => sprintf(
				'%s: <code>%s</code><br/>%s: %s<br/>%s: %s<br/>%s: %s<br/>%s: <code>%s</code>',
				__( 'File', 'wp-simple-firewall' ),
				$this->escape( $finding[ 'file' ] ),
				__( 'Name', 'wp-simple-firewall' ),
				$this->escape( $finding[ 'name' ] ),
				__( 'Type', 'wp-simple-firewall' ),
				$this->escape( $finding[ 'type_label' ] ),
				__( 'Hidden By', 'wp-simple-firewall' ),
				$this->escape( $hiddenBy ),
				__( 'Path', 'wp-simple-firewall' ),
				$this->escape( $finding[ 'path' ] )
			),
			'href' => $this->pluginsUrl( $finding[ 'type' ], $finding[ 'status' ] ),
		];
	}

	private function pluginsUrl( string $type, string $status ) :string {
		$url = Services::WpGeneral()->getAdminUrl( 'plugins.php' );
		if ( $type === 'mu-plugin' ) {
			return URL::Build( $url, [ 'plugin_status' => 'mustuse' ] );
		}
		if ( \in_array( $status, [ 'active', 'inactive' ], true ) ) {
			return URL::Build( $url, [ 'plugin_status' => $status ] );
		}
		return $url;
	}

	private function escape( string $value ) :string {
		return \htmlspecialchars( $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8' );
	}
}
