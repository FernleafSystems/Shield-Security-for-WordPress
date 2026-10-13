<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\EarlyLoading;

use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading\{
	DirectiveBlocks,
	Mechanism
};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DirectiveBlocksTest extends TestCase {

	private const STUB = '/var/www/html/shield-early-loader.php';

	public function testUserIniBodyParsesToTheStubPath() :void {
		$parsed = \parse_ini_string( DirectiveBlocks::userIni( self::STUB ), false, \INI_SCANNER_NORMAL );

		$this->assertSame( [ 'auto_prepend_file' => self::STUB ], $parsed );
	}

	public function testWindowsPathIsWrittenWithForwardSlashes() :void {
		$parsed = \parse_ini_string( DirectiveBlocks::userIni( 'C:\\sites\\wp\\shield-early-loader.php' ), false, \INI_SCANNER_NORMAL );

		$this->assertSame( 'C:/sites/wp/shield-early-loader.php', $parsed[ 'auto_prepend_file' ] );
	}

	public static function htaccessProvider() :array {
		return [
			'mod_php'          => [ 'apache2handler', 'Apache', [ 'mod_php5.c', 'mod_php7.c', 'mod_php.c' ], false ],
			'litespeed'        => [ 'litespeed', 'LiteSpeed', [ 'LiteSpeed', 'lsapi_module' ], true ],
			'apache + php-fpm' => [ 'fpm-fcgi', 'Apache', [], true ],
		];
	}

	#[DataProvider( 'htaccessProvider' )]
	public function testHtaccessSetsThePrependInEachModuleAndProtectsUserIni(
		string $sapi,
		string $software,
		array $expectedModules,
		bool $expectUserIniProtection
	) :void {
		$body = DirectiveBlocks::htaccess( self::STUB, Mechanism::detect( $sapi, '.user.ini', $software ), '.user.ini' );

		\preg_match_all( "#<IfModule ([^>]+)>\\s*php_value auto_prepend_file '([^']+)'\\s*</IfModule>#", $body, $matches );
		$this->assertSame( $expectedModules, $matches[ 1 ] );
		$this->assertSame( \array_fill( 0, \count( $expectedModules ), self::STUB ), $matches[ 2 ] );
		$this->assertSame( $expectUserIniProtection, \preg_match( '#<Files "\.user\.ini">.*Require all denied.*</Files>#s', $body ) === 1 );
	}

	public static function unsafePathProvider() :array {
		return [
			'single quote' => [ "/var/www/o'brien/shield-early-loader.php" ],
			'double quote' => [ '/var/www/"x"/shield-early-loader.php' ],
			'newline'      => [ "/var/www/x\nphp_value auto_prepend_file /tmp/x.php" ],
			'carriage ret' => [ "/var/www/x\r/shield-early-loader.php" ],
			'nul'          => [ "/var/www/x\0/shield-early-loader.php" ],
			'relative'     => [ 'shield-early-loader.php' ],
			'empty'        => [ '' ],
		];
	}

	#[DataProvider( 'unsafePathProvider' )]
	public function testUnsafePathsAreRefused( string $path ) :void {
		$this->assertFalse( DirectiveBlocks::isSafePath( $path ) );
		$this->expectException( \InvalidArgumentException::class );
		DirectiveBlocks::userIni( $path );
	}
}
