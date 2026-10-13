<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\EarlyLoading;

use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading\{
	MalformedBlockException,
	MarkedBlock
};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MarkedBlockTest extends TestCase {

	private const WP_RULES = "# BEGIN WordPress\nRewriteEngine On\nRewriteRule . /index.php [L]\n# END WordPress\n";

	public function testApplyAppendsOnceAndReplacesOnReapply() :void {
		$block = MarkedBlock::htaccess();

		$first = $block->apply( self::WP_RULES, 'php_value auto_prepend_file \'/a.php\'' );
		$second = $block->apply( $first, 'php_value auto_prepend_file \'/b.php\'' );

		$this->assertStringStartsWith( self::WP_RULES, $first );
		$this->assertTrue( $block->has( $second ) );
		$this->assertSame( 1, \substr_count( $second, '/b.php' ) );
		$this->assertStringNotContainsString( '/a.php', $second );
		$this->assertSame( \str_replace( '/a.php', '/b.php', $first ), $second );
	}

	public function testRemoveLeavesOtherContentIntact() :void {
		$block = MarkedBlock::htaccess();
		$withBlock = $block->apply( self::WP_RULES."\nOptions -Indexes\n", 'php_value auto_prepend_file \'/a.php\'' );

		$removed = $block->remove( $withBlock );

		$this->assertFalse( $block->has( $removed ) );
		$this->assertSame( self::WP_RULES."\nOptions -Indexes\n", \rtrim( $removed, "\n" )."\n" );
	}

	public function testBlockInsertedInTheMiddleIsRemovedCleanly() :void {
		$block = MarkedBlock::userIni();
		$content = "memory_limit = 256M\n; BEGIN Shield Early Loading\nauto_prepend_file = '/a.php'\n; END Shield Early Loading\nupload_max_filesize = 8M\n";

		$this->assertSame( "memory_limit = 256M\nupload_max_filesize = 8M\n", $block->remove( $content ) );
	}

	public function testRemoveLeavesTheRestOfTheFileExactlyAsItWas() :void {
		$block = MarkedBlock::htaccess();
		$users = "RewriteEngine On\r\n\r\n\r\n\r\n# keep this spacing\r\n";

		$this->assertSame( $users, $block->remove( $block->apply( $users, "php_value auto_prepend_file '/a.php'" ) ) );
	}

	public function testCrlfContentIsRecognised() :void {
		$block = MarkedBlock::userIni();
		$content = "memory_limit = 256M\r\n; BEGIN Shield Early Loading\r\nauto_prepend_file = '/a.php'\r\n; END Shield Early Loading\r\n";

		$this->assertTrue( $block->has( $content ) );
		$this->assertFalse( $block->hasForeignPrepend( $content ) );
		$this->assertSame( "memory_limit = 256M\r\n", $block->remove( $content ) );
	}

	public static function foreignPrependProvider() :array {
		return [
			'htaccess other plugin'      => [ 'htaccess', "# Wordfence WAF\n<IfModule mod_php.c>\n\tphp_value auto_prepend_file '/w.php'\n</IfModule>\n", true ],
			'htaccess admin value'       => [ 'htaccess', "php_admin_value auto_prepend_file /x.php\n", true ],
			'htaccess commented'         => [ 'htaccess', "# php_value auto_prepend_file '/x.php'\n", false ],
			'htaccess only shield block' => [ 'htaccess', "# BEGIN Shield Early Loading\nphp_value auto_prepend_file '/s.php'\n# END Shield Early Loading\n", false ],
			'ini other plugin'           => [ 'ini', "; BEGIN AP Patchstack\nauto_prepend_file = '/p.php'\n; END AP Patchstack\n", true ],
			'ini none value'             => [ 'ini', "auto_prepend_file=none\n", true ],
			'ini commented'              => [ 'ini', ";auto_prepend_file = '/x.php'\n", false ],
			'ini unrelated'              => [ 'ini', "auto_append_file = '/x.php'\nmemory_limit=1G\n", false ],
		];
	}

	#[DataProvider( 'foreignPrependProvider' )]
	public function testForeignPrependDetection( string $type, string $content, bool $expected ) :void {
		$block = $type === 'htaccess' ? MarkedBlock::htaccess() : MarkedBlock::userIni();

		$this->assertSame( $expected, $block->hasForeignPrepend( $content ) );
	}

	public static function malformedProvider() :array {
		return [
			'begin only'   => [ "# BEGIN Shield Early Loading\nphp_value auto_prepend_file '/s.php'\n" ],
			'end only'     => [ "# END Shield Early Loading\n" ],
			'two blocks'   => [ "# BEGIN Shield Early Loading\n# END Shield Early Loading\n# BEGIN Shield Early Loading\n# END Shield Early Loading\n" ],
			'nested begin' => [ "# BEGIN Shield Early Loading\n# BEGIN Shield Early Loading\n# END Shield Early Loading\n# END Shield Early Loading\n" ],
		];
	}

	#[DataProvider( 'malformedProvider' )]
	public function testMalformedMarkersAreNeverEdited( string $content ) :void {
		$this->expectException( MalformedBlockException::class );
		MarkedBlock::htaccess()->apply( $content, 'x' );
	}
}
