<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops\Select;
use FernleafSystems\Wordpress\Services\Services;

class PageStats extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_stats';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/stats.twig';

	protected function getRenderData() :array {
		$statsVars = $this->build();
		return [
			'flags'   => [
				'has_stats' => !empty( $statsVars[ 'stats' ] )
			],
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'clipboard-data-fill' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Simple Stats', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Some basic stats - this is in beta and will be developed over time.', 'wp-simple-firewall' ),

				'no_stats' => __( 'No stats yet. It wont take long though, so check back here soon.', 'wp-simple-firewall' )
			],
			'vars'    => $statsVars,
		];
	}

	public function build() :array {
		return [
			'stats'          => $this->buildStats(),
			'stat_intervals' => [
				'days_1'   => __( '24 Hours', 'wp-simple-firewall' ),
				'days_7'   => __( '7 Days', 'wp-simple-firewall' ),
				'months_1' => __( '1 Month', 'wp-simple-firewall' ),
				'lifetime' => __( 'Lifetime', 'wp-simple-firewall' ),
			]
		];
	}

	private function buildStats() :array {
		$allStats = [];

		foreach ( $this->getAllEvents() as $eventSection ) {
			$stats = [];
			foreach ( $eventSection[ 'events' ] as $event ) {
				$sums = $this->buildSums( $event );
				if ( !empty( \array_filter( $sums ) ) ) {
					$stats[ $event ] = [
						'key'    => $event,
						'name'   => self::con()->comps->events->getEventName( $event ),
						'counts' => $this->buildSums( $event ),
					];
				}
			}

			if ( !empty( $stats ) ) {
				$eventSection[ 'events' ] = $stats;
				$allStats[] = $eventSection;
			}
		}

		return $allStats;
	}

	private function buildSums( string $event ) :array {
		/** @var Select $selector */
		$selector = self::con()->db_con->events->getQuerySelector();
		$carbon = Services::Request()->carbon( true );
		return \array_map(
			'number_format',
			[
				'lifetime' => $selector->sumEvent( $event ),
				'months_1' => $selector
					->filterByCreatedAt( ( clone $carbon )->subDays( 30 )->startOfDay()->timestamp, '>' )
					->sumEvent( $event ),
				'days_7'   => $selector
					->filterByCreatedAt( ( clone $carbon )->subDays( 7 )->startOfDay()->timestamp, '>' )
					->sumEvent( $event ),
				'days_1'   => $selector
					->filterByCreatedAt( ( clone $carbon )->subHours( 24 )->timestamp, '>' )
					->sumEvent( $event ),
			]
		);
	}

	private function getAllEvents() :array {
		return [
			[
				'title'  => __( 'IP Offenses', 'wp-simple-firewall' ),
				'events' => [
					'ip_offense',
					'conn_kill',
					'ip_blocked',
				]
			],
			[
				'title'  => __( 'Bot Tracking', 'wp-simple-firewall' ),
				'events' => [
					'antibot_fail',
					'antibot_pass',
					'bottrack_404',
					'bottrack_fakewebcrawler',
					'bottrack_linkcheese',
					'bottrack_loginfailed',
					'bottrack_logininvalid',
					'bottrack_xmlrpc',
					'bottrack_invalidscript',
				]
			],
			[
				'title'  => __( 'Comment SPAM', 'wp-simple-firewall' ),
				'events' => [
					'spam_block_antibot',
					'spam_block_bot',
					'spam_block_human',
				]
			],
			[
				'title'  => __( 'Login Guard', 'wp-simple-firewall' ),
				'events' => [
					'cooldown_fail',
					'honeypot_fail',
					'botbox_fail',
					'login_block',
					'2fa_success',
				]
			],
		];
	}
}
