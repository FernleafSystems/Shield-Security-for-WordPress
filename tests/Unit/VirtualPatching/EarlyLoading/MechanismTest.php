<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\EarlyLoading;

use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading\{
	Mechanism,
	MechanismKind
};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MechanismTest extends TestCase {

	public static function environmentProvider() :array {
		return [
			'apache mod_php'           => [ 'apache2handler', '.user.ini', 'Apache/2.4.58', MechanismKind::ApacheModPhp, true, false, true ],
			'litespeed with user ini'  => [ 'litespeed', '.user.ini', 'LiteSpeed', MechanismKind::Litespeed, true, true, true ],
			'litespeed without'        => [ 'litespeed', '', 'LiteSpeed', MechanismKind::Litespeed, true, false, true ],
			'nginx php-fpm'            => [ 'fpm-fcgi', '.user.ini', 'nginx/1.27.0', MechanismKind::UserIni, false, true, false ],
			'apache php-fpm'           => [ 'fpm-fcgi', '.user.ini', 'Apache/2.4.58 (Ubuntu)', MechanismKind::UserIni, false, true, true ],
			'apache suphp cgi'         => [ 'cgi', '.user.ini', 'Apache', MechanismKind::UserIni, false, true, true ],
			'fpm with user ini off'    => [ 'fpm-fcgi', '', 'nginx', MechanismKind::Manual, false, false, false ],
			'fpm with unsafe ini name' => [ 'fpm-fcgi', '../php.ini', 'nginx', MechanismKind::Manual, false, false, false ],
			'cli'                      => [ 'cli', '.user.ini', '', MechanismKind::Manual, false, false, false ],
		];
	}

	#[DataProvider( 'environmentProvider' )]
	public function testDetectChoosesFilesForTheSapi(
		string $sapi,
		string $userIni,
		string $software,
		MechanismKind $expectedKind,
		bool $htaccessPrepend,
		bool $userIniWritten,
		bool $htaccessWritten
	) :void {
		$mechanism = Mechanism::detect( $sapi, $userIni, $software );

		$this->assertSame( $expectedKind, $mechanism->kind() );
		$this->assertSame( $htaccessPrepend, $mechanism->kind()->setsPrependInHtaccess() );
		$this->assertSame( $userIniWritten, $mechanism->writesUserIni() );
		$this->assertSame( $htaccessWritten, $mechanism->writesHtaccess() );
	}

	public function testCacheSecondsCoversTheUserIniCache() :void {
		$this->assertSame( 0, MechanismKind::ApacheModPhp->cacheSeconds( 300 ) );
		$this->assertSame( 300, MechanismKind::UserIni->cacheSeconds( 60 ) );
		$this->assertSame( 900, MechanismKind::UserIni->cacheSeconds( 900 ) );
		$this->assertSame( 300, MechanismKind::Litespeed->cacheSeconds( 0 ) );
	}
}
