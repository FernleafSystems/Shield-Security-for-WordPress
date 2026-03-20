<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

class LocalSiteDefinitions {

	public static function dev() :LocalSiteDefinition {
		return new LocalSiteDefinition(
			'dev',
			'Local dev site',
			'shield-local-site',
			'http://127.0.0.1:8888',
			'127.0.0.1',
			8888,
			'shield_local_site',
			'Shield Local Dev Site'
		);
	}

	public static function test() :LocalSiteDefinition {
		return new LocalSiteDefinition(
			'test',
			'Local test site',
			'shield-test-site',
			'http://127.0.0.1:8889',
			'127.0.0.1',
			8889,
			'shield_test_site',
			'Shield Local Test Site'
		);
	}
}
