<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use Carbon\CarbonInterface;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\DashboardRecentEventsDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class DashboardRecentEventsDataBuilderIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'events' );
	}

	public function test_dashboard_reads_latest_occurrences_in_one_query_including_spam_subtypes() :void {
		$builder = new DashboardRecentEventsDataBuilder();
		$keys = \array_column( $builder->build(), 'key' );
		$spamKeys = [ 'spam_block_antibot', 'spam_block_bot', 'spam_block_cooldown', 'spam_block_human', 'spam_block_humanrepeated' ];
		$handler = self::con()->db_con->events;
		$handler->getQueryDeleter()->filterByEvents( \array_merge( $keys, $spamKeys ) )->all();
		$this->assertSame( 0, $handler->getQuerySelector()->filterByEvents( \array_merge( $keys, $spamKeys ) )->count() );
		$eventsService = self::con()->comps->events;
		foreach ( \array_merge( \array_diff( $keys, [ 'comment_spam_block' ] ), $spamKeys ) as $key ) {
			$this->assertTrue( $eventsService->getEventDef( $key )[ 'stat' ], $key );
		}
		$this->assertFalse( $eventsService->getEventDef( 'comment_spam_block' )[ 'stat' ] );

		$now = Services::Request()->carbon();
		$latestAt = $now->getTimestamp() - 120;
		foreach ( [
			[ 'login_success', $latestAt ],
			[ 'login_success', $latestAt - 3600 ], // Newer row ID must not override the actual latest occurrence.
			[ 'spam_block_human', $latestAt - 600 ],
			[ 'spam_block_antibot', $latestAt ],
			[ 'spam_block_antibot', $latestAt ], // Timestamp ties must not duplicate tiles.
			[ 'scan_run', $now->getTimestamp() - 700000 ],
			[ 'options_exported', $now->getTimestamp() ], // Unselected event must not affect a tile.
		] as [ $event, $timestamp ] ) {
			$record = $handler->getRecord();
			$record->event = $event;
			$record->count = 1;
			$record->created_at = $timestamp;
			$this->assertTrue( $handler->getQueryInserter()->insert( $record ) );
		}

		$wpdb = Services::WpDb()->loadWpdb();
		$queriesBefore = $wpdb->num_queries;
		$items = \array_column( $builder->build(), null, 'key' );
		$this->assertSame( 1, $wpdb->num_queries - $queriesBefore );
		$this->assertSame( $keys, \array_keys( $items ) );
		$this->assertCount( 12, $items );
		$this->assertTrue( $items[ 'login_success' ][ 'has_record' ] );
		$this->assertSame(
			( clone $now )->setTimestamp( $latestAt )->diffForHumans( $now, CarbonInterface::DIFF_RELATIVE_TO_NOW, false, 2 ),
			$items[ 'login_success' ][ 'time_ago' ]
		);
		$this->assertSame( $items[ 'login_success' ][ 'time_ago' ], $items[ 'comment_spam_block' ][ 'time_ago' ] );
		$this->assertSame( 120, $items[ 'scan_run' ][ 'recency_hue' ] );
		$this->assertFalse( $items[ 'firewall_block' ][ 'has_record' ] );
		$this->assertSame( $items[ 'firewall_block' ][ 'time_ago' ], $items[ 'ip_blocked' ][ 'time_ago' ] );
	}
}
