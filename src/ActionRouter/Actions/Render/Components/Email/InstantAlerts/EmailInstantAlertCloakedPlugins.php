<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\CloakedPluginFinding;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-import-type CloakedPluginAlertData from CloakedPluginFinding
 */
class EmailInstantAlertCloakedPlugins extends EmailInstantAlertBase {

	public const SLUG = 'email_instant_alert_cloaked_plugins';

	protected function getBodyData() :array {
		return Services::DataManipulation()->mergeArraysRecursive( parent::getBodyData(), [
			'strings' => [
				'intro' => [
					__( 'A plugin file is present on disk but is cloaked from the WordPress admin plugin list.', 'wp-simple-firewall' ),
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
				__( 'Cloaked Plugin Findings', 'wp-simple-firewall' ),
				\array_map(
					fn( array $finding ) :array => $this->buildFindingItem( $finding ),
					$this->cloakedPluginAlertData()
				)
			),
		];
	}

	/**
	 * @return list<CloakedPluginAlertData>
	 */
	private function cloakedPluginAlertData() :array {
		/** @var list<CloakedPluginAlertData> $cloakedPlugins */
		$cloakedPlugins = $this->action_data[ 'alert_data' ][ 'hidden_plugins' ];
		return $cloakedPlugins;
	}

	/**
	 * @param CloakedPluginAlertData $finding
	 */
	private function buildFindingItem( array $finding ) :array {
		$cloakedBy = \implode( ', ', $finding[ 'hidden_by_labels' ] );

		return $this->alertItem(
			[
				$this->alertLine( __( 'File', 'wp-simple-firewall' ), $finding[ 'file' ], self::LINE_STYLE_CODE ),
				$this->alertLine( __( 'Name', 'wp-simple-firewall' ), $finding[ 'name' ] ),
				$this->alertLine( __( 'Type', 'wp-simple-firewall' ), $finding[ 'type_label' ] ),
				$this->alertLine( __( 'Hidden because', 'wp-simple-firewall' ), $cloakedBy ),
				$this->alertLine( __( 'Path', 'wp-simple-firewall' ), $finding[ 'location' ], self::LINE_STYLE_CODE ),
			],
			self::con()->plugin_urls->cloakedPlugins(),
			__( 'Review Cloaked Plugins', 'wp-simple-firewall' )
		);
	}
}
