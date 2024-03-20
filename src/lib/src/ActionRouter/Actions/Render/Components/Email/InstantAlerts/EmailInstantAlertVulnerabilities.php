<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class EmailInstantAlertVulnerabilities extends InstantAlertBase {

	public const SLUG = 'email_instant_alert_vulnerabilities';
	public const TEMPLATE = '/email/instant_alerts/instant_alert_vulnerabilities.twig';

	protected function getBodyData() :array {
		return [
			'strings' => [
				'details_below' => __( 'Details for the request are given below:', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'site_url' => Services::WpGeneral()->getHomeUrl(),
				'vulnerabilities' => [
					'plugins' => \array_map(
						function ( string $itemID ) {
							$VO = Services::WpPlugins()->getPluginAsVo( $itemID );
							return [
								'name'    => $VO->Name,
								'version' => $VO->Version,
								'href'    => URL::Build( 'https://shsec.io/shieldvulnerabilitylookup', [
									'type'    => 'plugin',
									'slug'    => $VO->slug,
									'version' => $VO->Version,
								] ),
							];
						},
						$this->action_data[ 'alert_data' ][ 'plugins' ]
					),
					'themes'  => \array_map(
						function ( string $itemID ) {
							$VO = Services::WpThemes()->getThemeAsVo( $itemID );
							return [
								'name'    => $VO->Name,
								'version' => $VO->Version,
								'href'    => URL::Build( 'https://shsec.io/shieldvulnerabilitylookup', [
									'type'    => 'theme',
									'slug'    => $VO->slug,
									'version' => $VO->Version,
								] ),
							];
						},
						$this->action_data[ 'alert_data' ][ 'themes' ]
					)
				],
			]
		];
	}
}