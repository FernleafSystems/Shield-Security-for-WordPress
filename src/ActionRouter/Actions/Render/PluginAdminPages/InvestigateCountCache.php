<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

trait InvestigateCountCache {

	private array $investigateCountRequestCache = [];

	protected function cachedCount( string $kind, string $subjectType, string $subjectId, callable $producer ) :int {
		$key = $this->buildInvestigateCountCacheKey( $kind, $subjectType, $subjectId );
		if ( empty( $key ) ) {
			return (int)$producer();
		}

		if ( \array_key_exists( $key, $this->investigateCountRequestCache ) ) {
			return $this->investigateCountRequestCache[ $key ];
		}

		try {
			$cached = get_transient( $key );
		}
		catch ( \Exception $e ) {
			$cached = false;
		}

		if ( $cached !== false ) {
			$value = (int)$cached;
			$this->investigateCountRequestCache[ $key ] = $value;
			return $value;
		}

		$value = (int)$producer();

		try {
			set_transient( $key, $value, $this->getInvestigateCountCacheTtl() );
		}
		catch ( \Exception $e ) {
		}

		$this->investigateCountRequestCache[ $key ] = $value;
		return $value;
	}

	protected function buildInvestigateCountCacheKey( string $kind, string $subjectType, string $subjectId ) :string {
		$kind = \strtolower( \preg_replace( '/[^a-z0-9_]+/', '_', \trim( $kind ) ) ?? '' );
		$subjectType = \strtolower( \preg_replace( '/[^a-z0-9_]+/', '_', \trim( $subjectType ) ) ?? '' );
		$subjectId = \strtolower( \trim( $subjectId ) );

		if ( empty( $kind ) || empty( $subjectType ) || empty( $subjectId ) ) {
			return '';
		}

		return self::con()->prefix( \sprintf(
			'investigate_count_%s_%s_%s',
			$kind,
			$subjectType,
			\md5( $subjectId )
		) );
	}

	protected function getInvestigateCountCacheTtl() :int {
		return 30;
	}
}
