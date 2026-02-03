<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;

class ChartsCustom extends Base {

	public const SLUG = 'render_charts_custom';
	public const TEMPLATE = '/wpadmin_pages/insights/reports/charts_custom.twig';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'select_events'   => __( 'Events', 'wp-simple-firewall' ),
				'select_interval' => __( 'Interval', 'wp-simple-firewall' ),
				'build_chart'     => __( 'Build Chart', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'events'   => $this->buildPossibleEvents(),
				'interval' => [
					'hourly'  => __( 'Hourly', 'wp-simple-firewall' ),
					'daily'   => __( 'Daily', 'wp-simple-firewall' ),
					'weekly'  => __( 'Weekly', 'wp-simple-firewall' ),
					'monthly' => __( 'Monthly', 'wp-simple-firewall' ),
					'yearly'  => __( 'Yearly', 'wp-simple-firewall' ),
				],
			],
		];
	}

	/**
	 * Finds all available events logged in the DB and intersects this with all available Event names
	 * i.e. so you can only build charts of events with actual records
	 */
	private function buildPossibleEvents() :array {
		return \array_intersect_key(
			self::con()->comps->events->getEventNames(),
			\array_flip( self::con()
				->db_con
				->events
				->getQuerySelector()
				->getDistinctForColumn( 'event' ) )
		);
	}
}