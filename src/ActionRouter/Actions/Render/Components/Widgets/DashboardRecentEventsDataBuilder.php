<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use Carbon\CarbonInterface;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\DashboardEventIcons;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type DashboardRecentEvent array{
 *   key:string,
 *   label:string,
 *   time_ago:string,
 *   icon_class:string,
 *   has_record:bool,
 *   recency_hue:int,
 *   accessible_label:string
 * }
 */
class DashboardRecentEventsDataBuilder {

	use PluginControllerConsumer;

	// The aggregate comment_spam_block event has stat=false; its subtypes are persisted.
	private const COMMENT_SPAM_EVENTS = [
		'spam_block_antibot',
		'spam_block_bot',
		'spam_block_cooldown',
		'spam_block_human',
		'spam_block_humanrepeated',
	];

	/**
	 * @return list<DashboardRecentEvent>
	 */
	public function build() :array {
		$labels = [
			'firewall_block'           => __( 'Firewall Block', 'wp-simple-firewall' ),
			'login_success'            => __( 'Login Success', 'wp-simple-firewall' ),
			'login_block'              => __( 'Login Blocked', 'wp-simple-firewall' ),
			'conn_kill'                => __( 'Connection Killed', 'wp-simple-firewall' ),
			'ip_blocked'               => __( 'IP Blocked', 'wp-simple-firewall' ),
			'ip_offense'               => __( 'IP Offence', 'wp-simple-firewall' ),
			'block_register'           => __( 'Registration Blocked', 'wp-simple-firewall' ),
			'block_xml'                => __( 'XML-RPC Blocked', 'wp-simple-firewall' ),
			'comment_spam_block'       => __( 'Spam Comment Blocked', 'wp-simple-firewall' ),
			'scan_run'                 => __( 'Scan Run', 'wp-simple-firewall' ),
			'scan_items_found'         => __( 'Scan Items Found', 'wp-simple-firewall' ),
			'scan_item_repair_success' => __( 'File Repaired', 'wp-simple-firewall' ),
		];
		$latest = self::con()->db_con->events->getQuerySelector()->getLatestTimestampsForEvents(
			\array_merge( \array_keys( $labels ), self::COMMENT_SPAM_EVENTS )
		);
		foreach ( self::COMMENT_SPAM_EVENTS as $event ) {
			$latest[ 'comment_spam_block' ] = \max( $latest[ 'comment_spam_block' ] ?? 0, $latest[ $event ] ?? 0 );
		}

		$now = Services::Request()->carbon();
		$items = [];
		foreach ( $labels as $key => $label ) {
			$items[] = $this->buildItem( $key, $label, $latest[ $key ] ?? 0, $now );
		}
		return $items;
	}

	/**
	 * @return DashboardRecentEvent
	 */
	private function buildItem( string $key, string $label, int $latestAt, CarbonInterface $now ) :array {
		$hasRecord = $latestAt > 0;
		$ageSeconds = \max( 0, $now->getTimestamp() - $latestAt );
		$timeAgo = $hasRecord
			? ( clone $now )->subSeconds( $ageSeconds )->diffForHumans( $now, CarbonInterface::DIFF_RELATIVE_TO_NOW, false, 2 )
			: __( 'Not yet recorded', 'wp-simple-firewall' );
		/* translators: %1$s: event label, %2$s: latest event time */
		$accessibleLabel = \sprintf( __( '%1$s. Latest event %2$s.', 'wp-simple-firewall' ), $label, $timeAgo );

		return [
			'key'              => $key,
			'label'            => $label,
			'time_ago'         => $timeAgo,
			'icon_class'       => ( new DashboardEventIcons() )->iconClassForKey( $key ),
			'has_record'       => $hasRecord,
			// A logarithmic scale distinguishes seconds from minutes, then tapers to green at one week.
			'recency_hue'      => (int)\round( 120 * \log( \max( 1, \min( 604800, $ageSeconds ) ) ) / \log( 604800 ) ),
			'accessible_label' => $accessibleLabel,
		];
	}
}
