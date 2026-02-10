<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ActivityLog\EventSlugSearch;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class EventSlugSearchTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\stubs( [
			'_x' => fn( $text ) => $text,
		] );
	}

	public function test_tokenize_splits_on_whitespace() :void {
		$tokens = ( new EventSlugSearch() )->tokenize( 'login firewall blocked' );
		$this->assertSame( [ 'login', 'firewall', 'blocked' ], $tokens );
	}

	public function test_tokenize_filters_stopwords() :void {
		$tokens = ( new EventSlugSearch() )->tokenize( 'the login was blocked for admin' );
		$this->assertSame( [ 'login', 'blocked', 'admin' ], $tokens );
	}

	public function test_tokenize_filters_words_two_chars_or_fewer() :void {
		$tokens = ( new EventSlugSearch() )->tokenize( 'ab login cd' );
		$this->assertSame( [ 'login' ], $tokens );
	}

	public function test_tokenize_three_char_non_stopword_passes() :void {
		$tokens = ( new EventSlugSearch() )->tokenize( 'foo' );
		$this->assertSame( [ 'foo' ], $tokens );
	}

	public function test_tokenize_three_char_stopword_filtered() :void {
		$tokens = ( new EventSlugSearch() )->tokenize( 'are com how' );
		$this->assertSame( [], $tokens );
	}

	public function test_tokenize_empty_string() :void {
		$tokens = ( new EventSlugSearch() )->tokenize( '' );
		$this->assertSame( [], $tokens );
	}

	public function test_tokenize_whitespace_only() :void {
		$tokens = ( new EventSlugSearch() )->tokenize( '   ' );
		$this->assertSame( [], $tokens );
	}

	public function test_tokenize_all_stopwords() :void {
		$tokens = ( new EventSlugSearch() )->tokenize( 'the was for from' );
		$this->assertSame( [], $tokens );
	}

	public function test_tokenize_all_short_words() :void {
		$tokens = ( new EventSlugSearch() )->tokenize( 'ab cd ef' );
		$this->assertSame( [], $tokens );
	}

	public function test_tokenize_collapses_multiple_spaces() :void {
		$tokens = ( new EventSlugSearch() )->tokenize( 'login   firewall   blocked' );
		$this->assertSame( [ 'login', 'firewall', 'blocked' ], $tokens );
	}

	public function test_tokenize_handles_tabs_and_newlines() :void {
		$tokens = ( new EventSlugSearch() )->tokenize( "login\tfirewall\nblocked" );
		$this->assertSame( [ 'login', 'firewall', 'blocked' ], $tokens );
	}

	public function test_tokenize_preserves_original_case() :void {
		$tokens = ( new EventSlugSearch() )->tokenize( 'Login FireWall' );
		$this->assertSame( [ 'Login', 'FireWall' ], $tokens );
	}

	public function test_tokenize_stopword_matching_is_case_insensitive() :void {
		$tokens = ( new EventSlugSearch() )->tokenize( 'The Login Was' );
		$this->assertSame( [ 'Login' ], $tokens );
	}

	public function test_tokenize_reindexes_after_filtering() :void {
		$tokens = ( new EventSlugSearch() )->tokenize( 'the login the admin' );
		$this->assertSame( [ 0, 1 ], \array_keys( $tokens ) );
		$this->assertSame( [ 'login', 'admin' ], $tokens );
	}

	public function test_tokenize_mixed_stopwords_short_words_and_valid() :void {
		$tokens = ( new EventSlugSearch() )->tokenize( 'ab the wordfence is cd plugin' );
		$this->assertSame( [ 'wordfence', 'plugin' ], $tokens );
	}
}
