<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\RulesStorageHandler;

trait RuntimeRulesStorageAssertions {

	protected function loadStoredRuntimeRules() :array {
		return ( new RulesStorageHandler() )->loadRules( false )[ 'rules' ];
	}

	protected function rebuildAndLoadRuntimeRules() :array {
		$this->requireController()->rules->buildAndStore();

		return $this->loadStoredRuntimeRules();
	}

	protected function runtimeCustomRuleSlugs() :array {
		return \array_values( \array_filter(
			\array_map(
				static fn( array $rule ) :string => (string)( $rule[ 'slug' ] ?? '' ),
				$this->rebuildAndLoadRuntimeRules()
			),
			static fn( string $slug ) :bool => \strpos( $slug, 'custom/' ) === 0
		) );
	}

	protected function loadStoredRuntimeRuleBySlug( string $slug ) :array {
		foreach ( $this->loadStoredRuntimeRules() as $rule ) {
			if ( ( $rule[ 'slug' ] ?? '' ) === $slug ) {
				return $rule;
			}
		}

		$this->fail( \sprintf( 'Runtime rule "%s" was not found in stored rules.', $slug ) );
	}
}
