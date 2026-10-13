<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\EarlyLoading;

use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading\Mechanism;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MechanismTest extends TestCase {

	public static function environmentProvider() :array {
		return [
			'apache mod_php'           => [ 'apache2handler', '.user.ini', 'Apache/2.4.58', Mechanism::APACHE_MOD_PHP, true, false, true ],
			'litespeed with user ini'  => [ 'litespeed', '.user.ini', 'LiteSpeed', Mechanism::LITESPEED, true, true, true ],
			'litespeed without'        => [ 'litespeed', '', 'LiteSpeed', Mechanism::LITESPEED, true, false, true ],
			'nginx php-fpm'            => [ 'fpm-fcgi', '.user.ini', 'nginx/1.27.0', Mechanism::USER_INI, false, true, false ],
			'apache php-fpm'           => [ 'fpm-fcgi', '.user.ini', 'Apache/2.4.58 (Ubuntu)', Mechanism::USER_INI, false, true, true ],
			'apache suphp cgi'         => [ 'cgi', '.user.ini', 'Apache', Mechanism::USER_INI, false, true, true ],
			'fpm with user ini off'    => [ 'fpm-fcgi', '', 'nginx', Mechanism::MANUAL, false, false, false ],
			'fpm with unsafe ini name' => [ 'fpm-fcgi', '../php.ini', 'nginx', Mechanism::MANUAL, false, false, false ],
			'cli'                      => [ 'cli', '.user.ini', '', Mechanism::MANUAL, false, false, false ],
		];
	}

	#[DataProvider( 'environmentProvider' )]
	public function testDetectChoosesFilesForTheSapi(
		string $sapi,
		string $userIni,
		string $software,
		string $expectedKey,
		bool $htaccessPrepend,
		bool $userIniWritten,
		bool $htaccessWritten
	) :void {
		$mechanism = Mechanism::detect( $sapi, $userIni, $software );

		$this->assertSame( $expectedKey, $mechanism->key() );
		$this->assertSame( $expectedKey !== Mechanism::MANUAL, $mechanism->isAutomatic() );
		$this->assertSame( $htaccessPrepend, $mechanism->writesHtaccessPrepend() );
		$this->assertSame( $userIniWritten, $mechanism->writesUserIni() );
		$this->assertSame( $htaccessWritten, $mechanism->writesHtaccess() );
	}

	public function testCacheSecondsCoversTheUserIniCache() :void {
		$this->assertSame( 0, Mechanism::detect( 'apache2handler', '.user.ini', 'Apache' )->cacheSeconds( 300 ) );
		$this->assertSame( 300, Mechanism::detect( 'fpm-fcgi', '.user.ini', 'nginx' )->cacheSeconds( 60 ) );
		$this->assertSame( 900, Mechanism::detect( 'fpm-fcgi', '.user.ini', 'nginx' )->cacheSeconds( 900 ) );
		$this->assertSame( 300, Mechanism::detect( 'litespeed', '', 'LiteSpeed' )->cacheSeconds( 0 ) );
	}
}
