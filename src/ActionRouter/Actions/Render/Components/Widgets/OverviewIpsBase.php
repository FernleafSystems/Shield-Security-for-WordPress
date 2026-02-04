<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Services\Services;

abstract class OverviewIpsBase extends OverviewBase {

	public const TEMPLATE = '/wpadmin/components/widget/overview_ip_offenses.twig';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'no_ips' => __( 'There are no IPs available yet.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'ips' => \array_slice( \array_map(
					function ( $ip ) {
						return [
							'ip'      => $ip->ip,
							'ip_href' => self::con()->plugin_urls->ipAnalysis( $ip->ip ),
							'ago'     => Services::Request()
												 ->carbon()
												 ->setTimestamp( $ip->last_access_at )
												 ->diffForHumans()
						];
					},
					$this->getIPs()
				), 0, $this->action_data[ 'limit' ] ?? 5 ),
			],
		];
	}

	abstract protected function getIPs() :array;
}