<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Services\Services;

class Container extends Base {

	public const SLUG = 'ipanalyse_container';
	public const TEMPLATE = '/wpadmin/components/ip_analyse/container.twig';

	protected function getRenderData() :array {
		$ip = $this->action_data[ 'ip' ];
		if ( !Services::IP()->isValidIp( $ip ) ) {
			throw new \Exception( "A valid IP address wasn't provided." );
		}
		$actionRouter = self::con()->action_router;
		return [
			'content' => [
				'general'  => $actionRouter->render( General::class, [
					'ip' => $ip,
				] ),
				'signals'  => $actionRouter->render( BotSignals::class, [
					'ip' => $ip,
				] ),
				'sessions' => $actionRouter->render( Sessions::class, [
					'ip' => $ip,
				] ),
				'activity' => $actionRouter->render( Activity::class, [
					'ip' => $ip,
				] ),
				'traffic'  => $actionRouter->render( Traffic::class, [
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