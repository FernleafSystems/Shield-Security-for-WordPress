<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\CoolDownHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsController;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Services\Services;

class CommentSpamScannerIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private const SPAM_WORD = 'shi279spamterm';

	private array $optionSnapshot = [];

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'bot_signals' );
		$this->requireDb( 'ips' );
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'enable_antibot_comments',
			'comments_default_action_spam_bot',
			'enable_comments_human_spam_filter',
			'comments_default_action_human_spam',
			'trusted_commenter_minimum',
			'comments_cooldown',
			'last_comment_request_at',
			'antibot_minimum',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->resetBotSignalCache();
		$this->resetCooldownCache();
		$this->deleteSpamList();
	}

	public function tear_down() {
		$this->deleteSpamList();
		$this->resetBotSignalCache();
		$this->resetCooldownCache();
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		$this->restoreSelectedOptions( $this->optionSnapshot );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_bot_spam_uses_configured_status_and_stable_events() :void {
		$postId = $this->createCommentablePost();
		$this->configureCommentSpamOptions( [
			'enable_antibot_comments'           => 'Y',
			'comments_default_action_spam_bot'  => 'spam',
			'enable_comments_human_spam_filter' => 'N',
			'trusted_commenter_minimum'         => 100,
			'comments_cooldown'                 => 0,
		] );
		$this->captureShieldEvents();

		$forceBot = fn() => 101;
		add_filter( 'shield/antibot_score_minimum', $forceBot );
		try {
			$result = $this->scanAndInsertComment(
				$this->commentData( $postId, 'bot-commenter@example.test', 'ordinary comment body' )
			);
		}
		finally {
			remove_filter( 'shield/antibot_score_minimum', $forceBot );
		}

		$this->assertSame( 'spam', $result[ 'status' ] );
		$this->assertSame( '1', get_comment_meta( $result[ 'id' ], $this->requireController()->prefix( 'spam_antibot' ), true ) );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'spam_block_antibot' ) );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'comment_spam_block' ) );
	}

	public function test_human_dictionary_spam_uses_local_seed_and_stable_event_data() :void {
		$postId = $this->createCommentablePost();
		$this->seedSpamList( [ self::SPAM_WORD ] );
		$this->configureCommentSpamOptions( [
			'enable_antibot_comments'           => 'Y',
			'comments_default_action_spam_bot'  => 'spam',
			'enable_comments_human_spam_filter' => 'Y',
			'comments_default_action_human_spam'=> 'spam',
			'trusted_commenter_minimum'         => 100,
			'comments_cooldown'                 => 0,
			'antibot_minimum'                   => 0,
		] );
		$this->captureShieldEvents();

		$result = $this->scanAndInsertComment(
			$this->commentData( $postId, 'human-commenter@example.test', 'contains '.self::SPAM_WORD )
		);

		$this->assertSame( 'spam', $result[ 'status' ] );
		$this->assertSame( '1', get_comment_meta( $result[ 'id' ], $this->requireController()->prefix( 'spam_human' ), true ) );

		$events = $this->getCapturedEventsByKey( 'spam_block_human' );
		$this->assertCount( 1, $events );
		$this->assertSame( self::SPAM_WORD, $events[ 0 ][ 'meta' ][ 'audit_params' ][ 'word' ] ?? null );
		$this->assertSame( 'comment_content', $events[ 0 ][ 'meta' ][ 'audit_params' ][ 'key' ] ?? null );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'comment_spam_block' ) );
	}

	public function test_comment_cooldown_uses_configured_status_and_event() :void {
		$postId = $this->createCommentablePost();
		$this->configureCommentSpamOptions( [
			'enable_antibot_comments'           => 'Y',
			'comments_default_action_spam_bot'  => 'spam',
			'enable_comments_human_spam_filter' => 'Y',
			'comments_default_action_human_spam'=> 'spam',
			'trusted_commenter_minimum'         => 100,
			'comments_cooldown'                 => 30,
			'antibot_minimum'                   => 0,
		] );
		$this->primeCommentCooldownFlag();
		$this->captureShieldEvents();

		$result = $this->scanAndInsertComment(
			$this->commentData( $postId, 'cooldown-commenter@example.test', 'ordinary comment body' )
		);

		$this->assertSame( 'spam', $result[ 'status' ] );
		$this->assertSame( '1', get_comment_meta( $result[ 'id' ], $this->requireController()->prefix( 'spam_cooldown' ), true ) );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'spam_block_cooldown' ) );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'comment_spam_block' ) );
	}

	public function test_trusted_commenter_bypasses_human_spam_scan() :void {
		$postId = $this->createCommentablePost();
		$email = 'trusted-commenter@example.test';
		$this->seedSpamList( [ self::SPAM_WORD ] );
		$this->insertApprovedComment( $postId, $email );
		$this->configureCommentSpamOptions( [
			'enable_antibot_comments'           => 'Y',
			'enable_comments_human_spam_filter' => 'Y',
			'comments_default_action_human_spam'=> 'spam',
			'trusted_commenter_minimum'         => 1,
			'comments_cooldown'                 => 0,
			'antibot_minimum'                   => 0,
		] );
		$this->captureShieldEvents();

		$result = $this->scanAndInsertComment(
			$this->commentData( $postId, $email, 'contains '.self::SPAM_WORD )
		);

		$this->assertSame( '1', $result[ 'status' ] );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'spam_block_human' ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'comment_spam_block' ) );
	}

	public function test_disabled_human_spam_filter_does_not_mark_comment() :void {
		$postId = $this->createCommentablePost();
		$this->seedSpamList( [ self::SPAM_WORD ] );
		$this->configureCommentSpamOptions( [
			'enable_antibot_comments'           => 'Y',
			'enable_comments_human_spam_filter' => 'N',
			'comments_default_action_human_spam'=> 'spam',
			'trusted_commenter_minimum'         => 100,
			'comments_cooldown'                 => 0,
			'antibot_minimum'                   => 0,
		] );
		$this->captureShieldEvents();

		$result = $this->scanAndInsertComment(
			$this->commentData( $postId, 'disabled-human@example.test', 'contains '.self::SPAM_WORD )
		);

		$this->assertSame( '1', $result[ 'status' ] );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'spam_block_human' ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'comment_spam_block' ) );
	}

	public function test_disabled_antibot_filter_does_not_mark_or_fire_bot_events() :void {
		$postId = $this->createCommentablePost();
		$this->configureCommentSpamOptions( [
			'enable_antibot_comments'           => 'N',
			'comments_default_action_spam_bot'  => 'spam',
			'enable_comments_human_spam_filter' => 'N',
			'trusted_commenter_minimum'         => 100,
			'comments_cooldown'                 => 0,
		] );
		$this->captureShieldEvents();

		$forceBot = fn() => 101;
		add_filter( 'shield/antibot_score_minimum', $forceBot );
		try {
			$result = $this->scanAndInsertComment(
				$this->commentData( $postId, 'disabled-bot@example.test', 'ordinary comment body' )
			);
		}
		finally {
			remove_filter( 'shield/antibot_score_minimum', $forceBot );
		}

		$this->assertSame( '1', $result[ 'status' ] );
		$this->assertSame( '', get_comment_meta( $result[ 'id' ], $this->requireController()->prefix( 'spam_antibot' ), true ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'spam_block_antibot' ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'comment_spam_block' ) );
	}

	private function configureCommentSpamOptions( array $options ) :void {
		foreach ( $options as $key => $value ) {
			$this->requireController()->opts->optSet( (string)$key, $value );
		}
	}

	private function createCommentablePost() :int {
		return self::factory()->post->create( [
			'post_status'    => 'publish',
			'comment_status' => 'open',
		] );
	}

	private function commentData( int $postId, string $email, string $content, string $ip = '198.51.100.25' ) :array {
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD'  => 'POST',
				'REQUEST_URI'     => '/wp-comments-post.php',
				'SCRIPT_NAME'     => '/wp-comments-post.php',
				'SCRIPT_FILENAME' => '/wp-comments-post.php',
				'PHP_SELF'        => '/wp-comments-post.php',
				'REMOTE_ADDR'     => $ip,
			],
			[],
			[
				'comment_post_ID' => (string)$postId,
				'author'          => 'SHI-279 Commenter',
				'email'           => $email,
				'url'             => '',
				'comment'         => $content,
			],
			[
				'path' => '/wp-comments-post.php',
				'ip'   => $ip,
			]
		);

		return [
			'comment_post_ID'      => $postId,
			'comment_author'       => 'SHI-279 Commenter',
			'comment_author_email' => $email,
			'comment_author_url'   => '',
			'comment_author_IP'    => $ip,
			'comment_content'      => $content,
		];
	}

	private function scanAndInsertComment( array $commentData ) :array {
		$scanner = new Scanner();
		$status = $scanner->checkComment( '1', $commentData );
		$commentId = wp_insert_comment( \array_merge( $commentData, [
			'comment_approved' => $status,
		] ) );

		do_action( 'comment_post', $commentId, $status, $commentData );
		remove_action( 'comment_post', [ $scanner, 'insertExplanation' ], 9 );

		return [
			'id'     => (int)$commentId,
			'status' => (string)get_comment( $commentId )->comment_approved,
		];
	}

	private function insertApprovedComment( int $postId, string $email ) :void {
		wp_insert_comment( [
			'comment_post_ID'      => $postId,
			'comment_author'       => 'Trusted Commenter',
			'comment_author_email' => $email,
			'comment_author_url'   => '',
			'comment_author_IP'    => '198.51.100.20',
			'comment_content'      => 'trusted baseline',
			'comment_approved'     => 1,
		] );
	}

	private function seedSpamList( array $words ) :void {
		$file = $this->spamListFile();
		if ( !empty( $file ) ) {
			Services::WpFs()->putFileContent(
				$file,
				\implode( "\n", \array_map( 'base64_encode', $words ) ),
				true
			);
		}
	}

	private function primeCommentCooldownFlag() :void {
		$file = $this->requireController()->cache_dir_handler->cacheItemPath( 'mode.throttled_'.CoolDownHandler::CONTEXT_COMMENTS );
		Services::WpFs()->touch( $file, Services::Request()->ts() );
		$this->resetCooldownCache();
	}

	private function spamListFile() :string {
		return $this->requireController()->cache_dir_handler->cacheItemPath( 'spamblacklist.txt' );
	}

	private function deleteSpamList() :void {
		$file = $this->spamListFile();
		if ( !empty( $file ) ) {
			Services::WpFs()->deleteFile( $file );
		}
	}

	private function resetBotSignalCache() :void {
		$reflection = new \ReflectionClass( BotSignalsController::class );
		$property = $reflection->getProperty( 'isBots' );
		$property->setAccessible( true );
		$property->setValue( $this->requireController()->comps->bot_signals, [] );
	}

	private function resetCooldownCache() :void {
		$reflection = new \ReflectionClass( CoolDownHandler::class );
		$property = $reflection->getProperty( 'secondsSinceLastReq' );
		$property->setAccessible( true );
		$property->setValue( $this->requireController()->comps->cool_down, [] );
	}
}
