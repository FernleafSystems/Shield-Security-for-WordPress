<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Debug;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteQuery\BuildRecentActivity;
use FernleafSystems\Wordpress\Services\Services;

class DebugRecentEvents extends Actions\Render\BaseRender {

	use Actions\Traits\SecurityAdminNotRequired;

	public const SLUG = 'render_debug_recentevents';
	public const TEMPLATE = '/wpadmin/components/recent_events.twig';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'title_recent'        => __( 'Recent Events Log', 'wp-simple-firewall' ),
				'box_receve_subtitle' => sprintf( __( 'Some of the most recent %s events', 'wp-simple-firewall' ), self::con()->labels->Name ),
			],
			'vars'    => [
				'insight_events' => $this->getData()
			],
		];
	}

	private function getData() :array {
		$recent = [];
		foreach ( ( new BuildRecentActivity() )->build()[ 'items' ] as $item ) {
			$recent[ $item[ 'key' ] ] = [
				'name' => $item[ 'label' ],
				'val'  => $item[ 'has_record' ]
					? Services::WpGeneral()->getTimeStringForDisplay( $item[ 'latest_at' ] )
					: __( 'Not yet recorded', 'wp-simple-firewall' ),
			];
		}

		return $recent;
	}
}
