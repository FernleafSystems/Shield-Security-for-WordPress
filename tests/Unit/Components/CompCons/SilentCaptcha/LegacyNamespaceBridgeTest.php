<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\SilentCaptcha;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class LegacyNamespaceBridgeTest extends BaseUnitTest {

	/**
	 * @dataProvider provideBridgeClasses
	 */
	public function test_old_fqcn_autoloads_as_new_component_class( string $oldClass, string $newClass ) :void {
		$this->assertTrue( \class_exists( $oldClass ) );
		$this->assertInstanceOf( $newClass, new $oldClass() );
	}

	public function provideBridgeClasses() :array {
		return [
			'altcha handler'              => [
				\FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot\AltChaHandler::class,
				\FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\AltCha\AltChaHandler::class,
			],
			'altcha v2 pbkdf2'           => [
				\FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot\AltChaV2Pbkdf2::class,
				\FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\AltCha\AltChaV2Pbkdf2::class,
			],
			'insert notbot js'            => [
				\FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot\InsertNotBotJs::class,
				\FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\Assets\InsertNotBotJs::class,
			],
			'notbot handler'              => [
				\FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot\NotBotHandler::class,
				\FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\Signals\NotBotHandler::class,
			],
			'silentcaptcha complexity'    => [
				\FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot\SilentCaptchaComplexity::class,
				\FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\SilentCaptchaComplexity::class,
			],
			'test notbot loading'         => [
				\FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot\TestNotBotLoading::class,
				\FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\Diagnostics\TestNotBotLoading::class,
			],
		];
	}
}
