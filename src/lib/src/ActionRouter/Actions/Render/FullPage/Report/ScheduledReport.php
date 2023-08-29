<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Report;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\BaseFullPageRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\EventsParser;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data\BuildForStats;
use FernleafSystems\Wordpress\Services\Services;

class ScheduledReport extends BaseFullPageRender {

	public const SLUG = 'render_report_scheduled';
	public const TEMPLATE = '/pages/report/scheduled.twig';

	protected function getRenderData() :array {
		$WP = Services::WpGeneral();
		$req = Services::Request();

		$eventsParser = new EventsParser();
		$statsBuilder = new BuildForStats( $req->carbon()->subWeek()->timestamp, $req->ts() );
		return [
			'hrefs'   => [
			],
			'strings' => [
				'report_header_title' => __( 'Website Security Report', 'wp-simple-firewall' ),
				'stats'               => __( 'Statistics', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'generation_date' => $WP->getTimeStringForDisplay( null, false ),
				'site_url_host'   => \parse_url( $WP->getHomeUrl(), \PHP_URL_HOST ),
				'stats'           => [
					'security'    => [
						'title'   => __( 'Security Stats', 'wp-simple-firewall' ),
						'stats'   => $statsBuilder->build( \array_keys( $eventsParser->security() ) ),
						'neutral' => false,
					],
					'wordpress'   => [
						'title'   => __( 'WordPress Stats', 'wp-simple-firewall' ),
						'stats'   => $statsBuilder->build( \array_keys( $eventsParser->wordpress() ) ),
						'neutral' => true,
					],
					'accounts'    => [
						'title'   => __( 'User Accounts', 'wp-simple-firewall' ),
						'stats'   => $statsBuilder->build( \array_keys( $eventsParser->accounts() ) ),
						'neutral' => true,
					],
					'user_access' => [
						'title'   => __( 'User Access', 'wp-simple-firewall' ),
						'stats'   => $statsBuilder->build( \array_keys( $eventsParser->userAccess() ) ),
						'neutral' => true,
					],
				],
			],
		];
	}

	protected function getScripts() :array {
		$urlBuilder = self::con()->urls;
		$scripts = parent::getScripts();
		$scripts[ 50 ] = [
			'src' => $urlBuilder->forJs( 'u2f-bundle' ),
			'id'  => 'u2f-bundle',
		];
		$scripts[ 51 ] = [
			'src' => $urlBuilder->forJs( 'shield/login2fa' ),
			'id'  => 'shield/login2fa',
		];
		return $scripts;
	}

	protected function getStyles() :array {
		$styles = parent::getStyles();
		$styles[ 51 ] = [
			'href' => self::con()->urls->forCss( 'shield/login2fa' ),
			'id'   => 'shield/login2fa',
		];
		return $styles;
	}
}