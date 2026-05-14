<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\Core\BotTrackFakeWebCrawler,
	Processors\ProcessConditions,
	Processors\ResponseProcessor,
	RuleVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\OffenseTracker;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class BotTrackFakeWebCrawlerRuleBehaviorTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->seedCrawlerProviders( $this->crawlerProviders() );
	}

	public function tear_down() {
		Transient::Delete( 'apto_provider_ips' );
		$this->resetServiceProvidersMemoryCache();
		parent::tear_down();
	}

	/**
	 * @dataProvider fakeCrawlerUserAgentProvider
	 */
	public function test_full_rule_matches_fake_web_crawler_claim( string $userAgent ) :void {
		$this->prepareAnonymousRequest( $userAgent );

		$this->assertTrue( $this->evaluateFullRule() );
	}

	/**
	 * @dataProvider strictIdentityFalsePositiveUserAgentProvider
	 */
	public function test_full_rule_rejects_strict_identity_false_positives( string $userAgent ) :void {
		$this->prepareAnonymousRequest( $userAgent );

		$this->assertFalse( $this->evaluateFullRule() );
	}

	/**
	 * @dataProvider botTrackModeProvider
	 */
	public function test_fake_web_crawler_response_uses_bottrack_mode_contract(
		string $mode,
		int $expectedOffenseCount,
		bool $expectedBlock
	) :void {
		$this->enablePremiumCapabilities();
		$this->prepareAnonymousRequest( 'facebookexternalhit/1.1', $mode );
		$this->assertTrue( $this->evaluateFullRule() );

		$tracker = new OffenseTracker();
		$initialOffenseCount = $tracker->getOffenseCount();
		$this->captureShieldEvents();

		( new ResponseProcessor( $this->getRule() ) )
			->setThisRequest( $this->requireController()->this_req )
			->run();

		$events = $this->getCapturedEventsByKey( 'bottrack_fakewebcrawler' );
		$this->assertCount( 1, $events );
		$this->assertArrayHasKey( 'offense_count', $events[ 0 ][ 'meta' ] );
		$this->assertArrayHasKey( 'block', $events[ 0 ][ 'meta' ] );
		$this->assertSame( $expectedOffenseCount, (int)$events[ 0 ][ 'meta' ][ 'offense_count' ] );
		$this->assertSame( $expectedBlock, (bool)$events[ 0 ][ 'meta' ][ 'block' ] );
		$this->assertSame( $initialOffenseCount + $expectedOffenseCount, $tracker->getOffenseCount() );
	}

	public function test_fake_web_crawler_rule_does_not_match_disabled_or_bypassed_or_logged_in_requests() :void {
		$this->enablePremiumCapabilities();

		$this->prepareAnonymousRequest( 'facebookexternalhit/1.1', 'disabled' );
		$this->assertFalse( $this->evaluateFullRule() );

		$this->prepareAnonymousRequest( 'facebookexternalhit/1.1', 'log' );
		$this->requireController()->this_req->request_bypasses_all_restrictions = true;
		$this->assertFalse( $this->evaluateFullRule() );

		$userId = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->prepareAnonymousRequest( 'facebookexternalhit/1.1', 'log' );
		\wp_set_current_user( $userId );
		$this->assertFalse( $this->evaluateFullRule() );
	}

	public static function fakeCrawlerUserAgentProvider() :array {
		return [
			'facebook external hit'          => [ 'facebookexternalhit/1.1' ],
			'facebook external hit with URL' => [ 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)' ],
			'facebook catalog'               => [ 'facebookcatalog/1.0' ],
			'google desktop'                 => [ 'Mozilla/5.0 AppleWebKit/538.0 (KHTML, like Gecko; compatible; Googlebot/3.0; +http://www.google.com/bot.html) Chrome/145.0.0.0 Safari/538.0' ],
			'google mobile'                  => [ 'Mozilla/5.0 (Linux; Android 15; Pixel 9 Build/AP4A.250205.002) AppleWebKit/538.0 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/538.0 (compatible; Googlebot/3.0; +http://www.google.com/bot.html)' ],
			'google rare'                    => [ 'Googlebot/3.2.1 (+http://www.google.com/bot.html)' ],
			'google apis'                    => [ 'APIs-Google (+https://developers.google.com/webmasters/APIs-Google.html)' ],
			'google adsbot'                  => [ 'AdsBot-Google (+http://www.google.com/adsbot.html)' ],
			'google adsbot mobile'           => [ 'Mozilla/5.0 (Linux; Android 15; Pixel 9 Build/AP4A.250205.002) AppleWebKit/538.0 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/538.0 (compatible; AdsBot-Google-Mobile; +http://www.google.com/mobile/adsbot.html)' ],
			'google mediapartners'           => [ 'Mediapartners-Google' ],
			'google mediapartners compat'    => [ 'Mozilla/5.0 (compatible; Mediapartners-Google/3.0; +http://www.google.com/bot.html)' ],
			'bing desktop'                   => [ 'Mozilla/5.0 AppleWebKit/538.0 (KHTML, like Gecko; compatible; bingbot/3.0; +http://www.bing.com/bingbot.htm) Chrome/145.0.0.0 Safari/538.0' ],
			'bing mobile'                    => [ 'Mozilla/5.0 (Linux; Android 15; Pixel 9 Build/AP4A.250205.002) AppleWebKit/538.0 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/538.0 (compatible; bingbot/3.0; +http://www.bing.com/bingbot.htm)' ],
			'bing historical'                => [ 'Mozilla/5.0 (compatible; bingbot/3.2.1; +http://www.bing.com/bingbot.htm)' ],
			'apple desktop'                  => [ 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15 (Applebot/0.1; +http://www.apple.com/go/applebot)' ],
			'apple mobile'                   => [ 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_4_1 like Mac OS X) AppleWebKit/606.1.15 (KHTML, like Gecko) Version/18.4.1 Mobile/15E148 Safari/606.1 (Applebot/0.2; +http://www.apple.com/go/applebot)' ],
			'legacy crawler fallback'        => [ 'AhrefsBot/7.0' ],
		];
	}

	public static function strictIdentityFalsePositiveUserAgentProvider() :array {
		return [
			'apple preview combined social UA' => [ 'facebookexternalhit/1.1 Facebot Twitterbot/1.0' ],
			'prefixed facebook external hit'   => [ 'Mozilla/5.0 facebookexternalhit/1.1' ],
			'suffixed facebook external hit'   => [ 'facebookexternalhit/1.1 Facebot' ],
			'prefixed googlebot rare UA'       => [ 'Mozilla/5.0 Googlebot/2.1 (+http://www.google.com/bot.html)' ],
			'suffixed bingbot historical UA'   => [ 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Extra' ],
			'prefixed applebot UA'             => [ 'Preview Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15 (Applebot/0.1; +http://www.apple.com/go/applebot)' ],
		];
	}

	public function botTrackModeProvider() :array {
		return [
			'log'                  => [ 'log', 0, false ],
			'transgression-single' => [ 'transgression-single', 1, false ],
			'transgression-double' => [ 'transgression-double', 2, false ],
			'block'                => [ 'block', 1, true ],
		];
	}

	private function prepareAnonymousRequest( string $userAgent, string $trackOption = 'log' ) :void {
		$con = $this->requireController();
		\wp_set_current_user( 0 );
		$con->opts->optSet( 'track_fakewebcrawler', $trackOption );
		$con->this_req->request_bypasses_all_restrictions = false;
		$con->this_req->useragent = $userAgent;
	}

	private function evaluateFullRule() :bool {
		$con = $this->requireController();
		return ( new ProcessConditions( $this->getRule()->conditions ) )
			->setThisRequest( $con->this_req )
			->process();
	}

	private function getRule() :RuleVO {
		return ( new BotTrackFakeWebCrawler() )->build();
	}

	private function seedCrawlerProviders( array $providers ) :void {
		Transient::Set( 'apto_provider_ips', $providers, \DAY_IN_SECONDS );
		$this->resetServiceProvidersMemoryCache();
	}

	private function resetServiceProvidersMemoryCache() :void {
		$ref = new \ReflectionObject( Services::ServiceProviders() );
		if ( $ref->hasProperty( 'providers' ) ) {
			$prop = $ref->getProperty( 'providers' );
			$prop->setAccessible( true );
			$prop->setValue( Services::ServiceProviders(), null );
		}
	}

	private function crawlerProviders() :array {
		return [
			'services' => [],
			'crawlers' => [
				'ahrefs'   => [
					'name'         => 'Ahrefs',
					'host_pattern' => '#.+\\.ahrefs\\.com\\.?$#i',
					'agents'       => [
						'AhrefsBot',
					],
					'type'         => [
						'search',
					],
				],
				'apple'    => [
					'name'                         => 'AppleBot',
					'host_pattern'                 => '#.+\\.applebot\\.apple\\.com\\.?$#i',
					'agents'                       => [
						'Applebot/',
					],
					'identity_user_agent_patterns' => [
						'#^Mozilla/5\\.0 \\([^)]+\\) AppleWebKit/\\d+(?:\\.\\d+)* \\(KHTML, like Gecko\\)\\s*Version/\\d+(?:\\.\\d+)*(?: Mobile/[A-Za-z0-9._-]+)? Safari/\\d+(?:\\.\\d+)* \\(Applebot/\\d+(?:\\.\\d+)*; \\+http://www\\.apple\\.com/go/applebot\\)$#i',
					],
					'type'                         => [
						'search',
					],
				],
				'bing'     => [
					'name'                         => 'BingBot',
					'host_pattern'                 => '#.+\\.search\\.msn\\.com\\.?$#i',
					'agents'                       => [
						'bingbot',
					],
					'identity_user_agent_patterns' => [
						'#^Mozilla/5\\.0 AppleWebKit/\\d+(?:\\.\\d+)* \\(KHTML, like Gecko; compatible; bingbot/\\d+(?:\\.\\d+)*; \\+http://www\\.bing\\.com/bingbot\\.htm\\) Chrome/\\d+(?:\\.\\d+)* Safari/\\d+(?:\\.\\d+)*$#i',
						'#^Mozilla/5\\.0 \\(Linux; Android [^;()]+; [^)]+\\) AppleWebKit/\\d+(?:\\.\\d+)* \\(KHTML, like Gecko\\) Chrome/\\d+(?:\\.\\d+)* Mobile Safari/\\d+(?:\\.\\d+)* \\(compatible; bingbot/\\d+(?:\\.\\d+)*; \\+http://www\\.bing\\.com/bingbot\\.htm\\)$#i',
						'#^Mozilla/5\\.0 \\(compatible; bingbot/\\d+(?:\\.\\d+)*; \\+http://www\\.bing\\.com/bingbot\\.htm\\)$#i',
					],
					'type'                         => [
						'search',
					],
				],
				'facebook' => [
					'name'                         => 'Facebook',
					'host_pattern'                 => '#.+\\.(fbsv\\.net|facebook\\.com)\\.?$#i',
					'agents'                       => [
						'facebookexternalhit',
						'facebookcatalog',
					],
					'identity_user_agent_patterns' => [
						'#^facebookexternalhit/\\d+(?:\\.\\d+)*(?: \\(\\+http://www\\.facebook\\.com/externalhit_uatext\\.php\\))?$#i',
						'#^facebookcatalog/\\d+(?:\\.\\d+)*$#i',
					],
					'type'                         => [
						'social_media',
					],
				],
				'google'   => [
					'name'                         => 'GoogleBot',
					'host_pattern'                 => '#.+\\.google(bot)?\\.com\\.?$#i',
					'agents'                       => [
						'Googlebot',
						'APIs-Google',
						'AdsBot-Google',
						'Mediapartners-Google',
					],
					'identity_user_agent_patterns' => [
						'#^Mozilla/5\\.0 AppleWebKit/\\d+(?:\\.\\d+)* \\(KHTML, like Gecko; compatible; Googlebot/\\d+(?:\\.\\d+)*; \\+http://www\\.google\\.com/bot\\.html\\) Chrome/\\d+(?:\\.\\d+)* Safari/\\d+(?:\\.\\d+)*$#i',
						'#^Mozilla/5\\.0 \\(Linux; Android [^;()]+; [^)]+\\) AppleWebKit/\\d+(?:\\.\\d+)* \\(KHTML, like Gecko\\) Chrome/\\d+(?:\\.\\d+)* Mobile Safari/\\d+(?:\\.\\d+)* \\(compatible; Googlebot/\\d+(?:\\.\\d+)*; \\+http://www\\.google\\.com/bot\\.html\\)$#i',
						'#^Mozilla/5\\.0 \\(compatible; Googlebot/\\d+(?:\\.\\d+)*; \\+http://www\\.google\\.com/bot\\.html\\)$#i',
						'#^Googlebot/\\d+(?:\\.\\d+)* \\(\\+http://www\\.google\\.com/bot\\.html\\)$#i',
						'#^APIs-Google \\(\\+https://developers\\.google\\.com/webmasters/APIs-Google\\.html\\)$#i',
						'#^AdsBot-Google \\(\\+http://www\\.google\\.com/adsbot\\.html\\)$#i',
						'#^Mozilla/5\\.0 \\(Linux; Android [^;()]+; [^)]+\\) AppleWebKit/\\d+(?:\\.\\d+)* \\(KHTML, like Gecko\\) Chrome/\\d+(?:\\.\\d+)* Mobile Safari/\\d+(?:\\.\\d+)* \\(compatible; AdsBot-Google-Mobile; \\+http://www\\.google\\.com/mobile/adsbot\\.html\\)$#i',
						'#^Mediapartners-Google$#i',
						'#^.+ \\(compatible; Mediapartners-Google/\\d+(?:\\.\\d+)*; \\+http://www\\.google\\.com/bot\\.html\\)$#i',
					],
					'type'                         => [
						'search',
					],
				],
			],
		];
	}
}
