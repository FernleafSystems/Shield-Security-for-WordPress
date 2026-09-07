<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use Carbon\CarbonInterface;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\DashboardEventIcons;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type DashboardRecentEvent array{
 *   key:string,
 *   label:string,
 *   time_ago:string,
 *   icon_class:string,
 *   recency_hue:int,
 *   accessible_label:string
 * }
 */
class DashboardRecentEventsDataBuilder {

	/**
	 * Prototype display values. The dashboard will replace these with the event
	 * timestamp query when the prototype is promoted to live data.
	 *
	 * @return list<DashboardRecentEvent>
	 */
	public function build() :array {
		return [
			$this->buildItem(
				'firewall_block',
				__( 'Firewall Block', 'wp-simple-firewall' ),
				90
			),
			$this->buildItem(
				'login_success',
				__( 'Login Success', 'wp-simple-firewall' ),
				300
			),
			$this->buildItem(
				'login_block',
				__( 'Login Blocked', 'wp-simple-firewall' ),
				1080
			),
			$this->buildItem(
				'conn_kill',
				__( 'Connection Killed', 'wp-simple-firewall' ),
				1
			),
			$this->buildItem(
				'ip_blocked',
				__( 'IP Blocked', 'wp-simple-firewall' ),
				60
			),
			$this->buildItem(
				'ip_offense',
				__( 'IP Offence', 'wp-simple-firewall' ),
				2580
			),
			$this->buildItem(
				'block_register',
				__( 'Registration Blocked', 'wp-simple-firewall' ),
				7200
			),
			$this->buildItem(
				'block_xml',
				__( 'XML-RPC Blocked', 'wp-simple-firewall' ),
				21600
			),
			$this->buildItem(
				'comment_spam_block',
				__( 'Spam Comment Blocked', 'wp-simple-firewall' ),
				86400
			),
			$this->buildItem(
				'scan_run',
				__( 'Scan Run', 'wp-simple-firewall' ),
				172800
			),
			$this->buildItem(
				'scan_items_found',
				__( 'Scan Items Found', 'wp-simple-firewall' ),
				604800
			),
			$this->buildItem(
				'scan_item_repair_success',
				__( 'File Repaired', 'wp-simple-firewall' ),
				864000
			),
		];
	}

	/**
	 * @return DashboardRecentEvent
	 */
	private function buildItem( string $key, string $label, int $ageSeconds ) :array {
		$now = Services::Request()->carbon();
		$timeAgo = ( clone $now )->subSeconds( $ageSeconds )
								->diffForHumans( $now, CarbonInterface::DIFF_RELATIVE_TO_NOW, false, 2 );
		/* translators: %1$s: event label, %2$s: latest event time */
		$accessibleLabel = \sprintf( __( '%1$s. Latest event %2$s.', 'wp-simple-firewall' ), $label, $timeAgo );

		return [
			'key'              => $key,
			'label'            => $label,
			'time_ago'         => $timeAgo,
			'icon_class'       => ( new DashboardEventIcons() )->iconClassForKey( $key ),
			// A logarithmic scale distinguishes seconds from minutes, then tapers to green at one week.
			'recency_hue'      => (int)\round( 120 * \log( \max( 1, \min( 604800, $ageSeconds ) ) ) / \log( 604800 ) ),
			'accessible_label' => $accessibleLabel,
		];
	}
}
