<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Services\Services;

class Container extends Base {

	const SLUG = 'ipanalyse_container';
	const TEMPLATE = '/wpadmin_pages/insights/ips/ip_analyse/container.twig';

	protected function getRenderData() :array {
		$ip = $this->action_data[ 'ip' ];
		if ( !Services::IP()->isValidIp( $ip ) ) {
			throw new \Exception( "A valid IP address wasn't provided." );
		}
		$actionRouter = $this->getCon()
							 ->getModule_Insights()
							 ->getActionRouter();
		return [
			'content' => [
				'general'  => $actionRouter->render( General::SLUG, [
					'ip' => $ip,
				] ),
				'signals'  => $actionRouter->render( BotSignals::SLUG, [
					'ip' => $ip,
				] ),
				'sessions' => $actionRouter->render( Sessions::SLUG, [
					'ip' => $ip,
				] ),
				'activity' => $actionRouter->render( Activity::SLUG, [
					'ip' => $ip,
				] ),
				'traffic'  => $actionRouter->render( Traffic::SLUG, [
					'ip' => $ip,
				] ),
			],
			'strings' => [
				'title'        => sprintf( __( 'Info For IP Address %s', 'wp-simple-firewall' ), $ip ),
				'nav_signals'  => __( 'Bot Signals', 'wp-simple-firewall' ),
				'nav_general'  => __( 'General Info', 'wp-simple-firewall' ),
				'nav_sessions' => __( 'User Sessions', 'wp-simple-firewall' ),
				'nav_audit'    => __( 'Activity Log', 'wp-simple-firewall' ),
				'nav_traffic'  => __( 'Recent Traffic', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'ip' => $ip,
			],
		];
	}
}