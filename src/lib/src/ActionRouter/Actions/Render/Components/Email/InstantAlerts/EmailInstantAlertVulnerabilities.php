<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class EmailInstantAlertVulnerabilities extends EmailInstantAlertBase {

	public const SLUG = 'email_instant_alert_vulnerabilities';

	protected function getBodyData() :array {
		return Services::DataManipulation()->mergeArraysRecursive( parent::getBodyData(), [
			'strings' => [
				'intro' => [
					__( 'Vulnerabilities have just been detected on your site.', 'wp-simple-firewall' ),
					__( 'Please take urgent action to either upgrade any vulnerable items, or remove them from your site.', 'wp-simple-firewall' )
				],
				'outro' => [
					__( "Important: If you've set Shield to automatically upgrade vulnerable plugins, it may have been upgraded by the time you view this.", 'wp-simple-firewall' )
				],
			],
		] );
	}

	protected function buildAlertGroups() :array {
		$alertGroups = [];
		foreach ( \array_filter( $this->action_data[ 'alert_data' ] ) as $alertKey => $alertItems ) {
			$alertGroups[ $alertKey ] = [
				'title' => $this->titleFor( $alertKey ),
				'items' => []
			];

			$WPP = Services::WpPlugins();
			$WPT = Services::WpThemes();
			foreach ( $alertItems as $alertItem ) {
				if ( $alertKey === 'plugins' ) {
					$VO = $WPP->getPluginAsVo( $alertItem );
					$alertGroups[ $alertKey ][ 'items' ][ $alertItem ] = [
						'text' => sprintf( '%s v%s', $VO->Name, $VO->Version ),
						'href' => URL::Build( 'https://clk.shldscrty.com/shieldvulnerabilitylookup', [
							'type'    => 'plugin',
							'slug'    => $VO->slug,
							'version' => $VO->Version,
						] ),
					];
				}
				elseif ( $alertKey === 'themes' ) {
					$VO = $WPT->getThemeAsVo( $alertItem );
					$alertGroups[ $alertKey ][ 'items' ][ $alertItem ] = [
						'text' => sprintf( '%s v%s', $VO->Name, $VO->Version ),
						'href' => URL::Build( 'https://clk.shldscrty.com/shieldvulnerabilitylookup', [
							'type'    => 'plugin',
							'slug'    => $VO->slug,
							'version' => $VO->Version,
						] ),
					];
				}
			}
		}

		return $alertGroups;
	}

	private function titleFor( string $key ) :string {
		return [
				   'plugins' => __( 'Vulnerable Plugins', 'wp-simple-firewall' ),
				   'themes'  => __( 'Vulnerable Themes', 'wp-simple-firewall' ),
			   ][ $key ];
	}
}