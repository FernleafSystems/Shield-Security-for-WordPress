<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Scans\ForPluginTheme;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

abstract class PluginThemesBase extends Base {

	private static $wpOrgDataCache = false;

	protected function getRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), [
			'strings' => [
				'ptg_name'          => __( 'Plugin/Theme Guard', 'wp-simple-firewall' ),
				'ptg_not_available' => __( 'Scanning Plugin & Theme Files is only available with ShieldPRO.', 'wp-simple-firewall' ),
			],
			'flags'   => [
				'ptg_is_restricted' => !$this->con()->isPremiumActive(),
			],
			'vars'    => [
				'datatables_init' => ( new ForPluginTheme() )->build()
			]
		] );
	}

	protected function getVulnerabilities() :Scans\Wpv\ResultsSet {
		try {
			$vulnerable = $this->con()
							   ->getModule_HackGuard()
							   ->getScansCon()
							   ->WPV()
							   ->getResultsForDisplay();
		}
		catch ( \Exception $e ) {
			$vulnerable = new Scans\Wpv\ResultsSet();
		}
		return $vulnerable;
	}

	protected function getAbandoned() :Scans\Apc\ResultsSet {
		try {
			$abandoned = $this->con()
							  ->getModule_HackGuard()
							  ->getScansCon()
							  ->APC()
							  ->getResultsForDisplay();
		}
		catch ( \Exception $e ) {
			$abandoned = new Scans\Apc\ResultsSet();
		}
		return $abandoned;
	}

	/**
	 * @param WpPluginVo|WpThemeVo $item
	 */
	protected function getCachedFlags( $item ) :array {
		if ( !is_array( self::$wpOrgDataCache ) ) {
			self::$wpOrgDataCache = Transient::Get( 'apto-shield-plugintheme-flags-cache' );
			if ( !is_array( self::$wpOrgDataCache ) ) {
				self::$wpOrgDataCache = [];
			}
		}

		if ( !isset( self::$wpOrgDataCache[ $item->unique_id ] ) ) {
			self::$wpOrgDataCache[ $item->unique_id ] = [
				'is_wporg' => $item->isWpOrg(),
				'has_tag'  => $item->isWpOrg() && $item->svn_uses_tags,
			];
			Transient::Set( 'apto-shield-plugintheme-flags-cache', self::$wpOrgDataCache, \HOUR_IN_SECONDS );
		}

		return self::$wpOrgDataCache[ $item->unique_id ];
	}
}