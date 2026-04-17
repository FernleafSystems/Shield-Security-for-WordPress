<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Login\TwoFactor\Import;

class ProcessUserPage {

	public const PAGE_SIZE = 250;

	/**
	 * @return array{
	 *   users_processed:int,
	 *   users_with_source_state:int,
	 *   users_with_imports:int,
	 *   user_errors:int,
	 *   imported_factors:array<string, int>,
	 *   skipped_factors:array<string, array<string, int>>,
	 *   last_error:string
	 * }
	 */
	public function run( SupplierBridgeInterface $bridge, int $page ) :array {
		$summary = [
			'users_processed'       => 0,
			'users_with_source_state' => 0,
			'users_with_imports'    => 0,
			'user_errors'           => 0,
			'imported_factors'      => [],
			'skipped_factors'       => [],
			'last_error'            => '',
		];

		$processor = new ImportUserProcessor();

		foreach ( $this->loadUsers( $page ) as $user ) {
			++$summary[ 'users_processed' ];

			try {
				$result = $processor->process( $user, $bridge );
			}
			catch ( \Throwable $e ) {
				++$summary[ 'user_errors' ];
				$summary[ 'last_error' ] = $e->getMessage();
				continue;
			}

			if ( $result->hasSourceState ) {
				++$summary[ 'users_with_source_state' ];
			}

			if ( !empty( $result->importedFactorSlugs ) ) {
				++$summary[ 'users_with_imports' ];
			}

			foreach ( $result->importedFactorSlugs as $factorSlug ) {
				$summary[ 'imported_factors' ][ $factorSlug ] = (int)( $summary[ 'imported_factors' ][ $factorSlug ] ?? 0 ) + 1;
			}

			foreach ( $result->skippedFactorReasons as $factorSlug => $reason ) {
				$summary[ 'skipped_factors' ][ $factorSlug ][ $reason ]
					= (int)( $summary[ 'skipped_factors' ][ $factorSlug ][ $reason ] ?? 0 ) + 1;
			}
		}

		return $summary;
	}

	/**
	 * @return \WP_User[]
	 */
	private function loadUsers( int $page ) :array {
		$query = new \WP_User_Query( [
			'number'        => self::PAGE_SIZE,
			'paged'         => \max( 1, $page ),
			'fields'        => 'all',
			'orderby'       => 'ID',
			'order'         => 'ASC',
			'cache_results' => false,
		] );

		return \array_values( \array_filter(
			$query->get_results(),
			static fn( $user ) => $user instanceof \WP_User
		) );
	}
}
