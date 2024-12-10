<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\SiteHealth;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter\MeterOverallConfig;

class Analysis extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use SecurityAdminNotRequired;

	public const SLUG = 'render_site_health_analysis';
	public const TEMPLATE = '/wpadmin_pages/site_health/analysis.twig';
	public const CRITICAL_BOUNDARY = 4;

	protected function getRenderData() :array {
		$con = self::con();
		$allComponents = ( new Handler() )->getMeter( MeterOverallConfig::SLUG, false )[ 'components' ];
		return [
			'hrefs'   => [
				'dashboard_home' => $con->plugin_urls->adminHome(),
			],
			'strings' => [
				'title'                          => __( 'Site Security Summary', 'wp-simple-firewall' ),
				'go_to_shield_home'              => __( 'Click to view your security summary in-full', 'wp-simple-firewall' ),
				'subtitle_description'           => [
					__( 'The site security health check shows a high-level summary about your WordPress security configuration.', 'wp-simple-firewall' ),
				],
				'areas_to_improve'               => __( 'Areas for improvement', 'wp-simple-firewall' ),
				'areas_to_improve_desc'          => __( 'Areas which should be addressed as early as possible but may not be critical', 'wp-simple-firewall' ),
				'critical_areas_to_improve'      => __( 'Critical Items', 'wp-simple-firewall' ),
				'critical_areas_to_improve_desc' => __( 'Issues listed below should be addressed as quickly as possible', 'wp-simple-firewall' ),
				'to_address_this_issue'          => __( 'Click here for more details on this item', 'wp-simple-firewall' ),
				'passed_tests'                   => __( 'Passed tests' ),
				'powered_by'                     => sprintf( __( 'Powered by %s', 'wp-simple-firewall' ), $con->labels->Name )
			],
			'vars'    => [
				'protected_components'   => \array_filter( $allComponents, function ( $comp ) {
					return $comp[ 'is_protected' ];
				} ),
				'critical_components'    => \array_filter( $allComponents, function ( $comp ) {
					return !$comp[ 'is_protected' ] && $comp[ 'weight' ] >= self::CRITICAL_BOUNDARY;
				} ),
				'improvement_components' => \array_filter( $allComponents, function ( $comp ) {
					return !$comp[ 'is_protected' ] && $comp[ 'weight' ] < self::CRITICAL_BOUNDARY;
				} ),

			],
		];
	}
}