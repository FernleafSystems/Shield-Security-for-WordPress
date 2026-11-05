<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Charts\ChartOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\UiPreferences;

class ChartsTrends extends Base {

	public const SLUG = 'render_charts_trends';
	public const TEMPLATE = '/wpadmin_pages/insights/reports/charts_trends.twig';

	public static function renderStrings() :array {
		return [
			'events'         => __( 'Events', 'wp-simple-firewall' ),
			'period'         => __( 'Period', 'wp-simple-firewall' ),
			'update_chart'   => __( 'Update Chart', 'wp-simple-firewall' ),
			'selection_none' => __( 'Select one or more events to chart, then update the timeline.', 'wp-simple-firewall' ),
			'empty_state'    => __( 'Select one or more events and click Update Chart to render the trend line.', 'wp-simple-firewall' ),
		];
	}

	public static function clientStrings() :array {
		return [
			'selection_none'      => self::renderStrings()[ 'selection_none' ],
			'selection_one'       => __( '1 event selected.', 'wp-simple-firewall' ),
			'selection_many'      => __( '%s events selected.', 'wp-simple-firewall' ),
			'select_events_error' => __( 'Select at least one event to chart.', 'wp-simple-firewall' ),
			'loading'             => __( 'Loading chart...', 'wp-simple-firewall' ),
			'chart_error'         => __( 'There was a problem loading this chart.', 'wp-simple-firewall' ),
		];
	}

	/**
	 * The chart opens drawn: on the period and events last charted, or every event over the default period.
	 */
	protected function getRenderData() :array {
		$last = ( new UiPreferences() )->getReportsTrends();
		$lastEvents = $last === null ? [] : ChartOptions::normalizeEventKeys( $last[ 'events' ] );
		$lastPeriod = $last === null ? '' : ChartOptions::normalizePeriodKey( $last[ 'period' ] );

		$events = ChartOptions::buildSelectableEvents();
		foreach ( $events as &$event ) {
			$event[ 'is_selected' ] = empty( $lastEvents ) || \in_array( $event[ 'key' ], $lastEvents, true );
		}
		unset( $event );

		$periods = ChartOptions::buildSelectablePeriods();
		if ( $lastPeriod !== '' ) {
			foreach ( $periods as &$period ) {
				$period[ 'is_default' ] = $period[ 'key' ] === $lastPeriod;
			}
			unset( $period );
		}

		return [
			'strings' => self::renderStrings(),
			'vars'    => [
				'events'  => $events,
				'periods' => $periods,
			],
		];
	}
}
