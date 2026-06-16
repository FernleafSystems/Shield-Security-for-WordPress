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
			'hidden_plugins' => $this->alertGroup(
				__( 'Hidden Plugin Findings', 'wp-simple-firewall' ),
				\array_map(
					fn( array $finding ) :array => $this->buildFindingItem( $finding ),
					$this->hiddenPluginAlertData()
				)
			),
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

		return $this->alertItem(
			[
				$this->alertLine( __( 'File', 'wp-simple-firewall' ), $finding[ 'file' ], self::LINE_STYLE_CODE ),
				$this->alertLine( __( 'Name', 'wp-simple-firewall' ), $finding[ 'name' ] ),
				$this->alertLine( __( 'Type', 'wp-simple-firewall' ), $finding[ 'type_label' ] ),
				$this->alertLine( __( 'Hidden By', 'wp-simple-firewall' ), $hiddenBy ),
				$this->alertLine( __( 'Location', 'wp-simple-firewall' ), $finding[ 'location' ], self::LINE_STYLE_CODE ),
			],
			$this->pluginsUrl( $finding[ 'type' ], $finding[ 'status' ] )
		);
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
}
