<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery;

/**
 * One fetch of delivered rule files for the installed plugins and themes, within a fixed time budget. It asks
 * first about those with a stored file, so a withdrawal or mode change reaches the site soonest, then those never
 * asked about, then the longest unasked. It stops at the first transport failure; what it leaves, the next run
 * takes.
 *
 * A file the same as the stored one needs no signature check. Any other is verified before it is stored, and one
 * that orders below the stored file is left: a stale edge copy looks the same while a purge spreads, so it isn't a
 * problem, but neither is it a successful refresh. A 404 never removes a stored file; only a newer signed one can.
 */
class RuleFeed {

	public const BUDGET_SECONDS = 20;

	private FeedClient $client;

	private RuleFileStore $store;

	private RuleFileVerifier $verifier;

	private int $schema;

	/**
	 * @var \Closure():float seconds, for the budget
	 */
	private \Closure $clock;

	public function __construct( FeedClient $client, RuleFileStore $store, RuleFileVerifier $verifier, int $schema, ?\Closure $clock = null ) {
		$this->client = $client;
		$this->store = $store;
		$this->verifier = $verifier;
		$this->schema = $schema;
		$this->clock = $clock ?? static fn() :float => \microtime( true );
	}

	/**
	 * @param array<string, array{kind: string, slug: string}> $assets the plugins and themes to ask about
	 */
	public function fetch( array $assets, FeedState $state, int $now ) :void {
		$started = ( $this->clock )();
		$retired = false;
		foreach ( $this->order( $assets, $state ) as $asset ) {
			if ( ( $this->clock )() - $started >= self::BUDGET_SECONDS ) {
				break;
			}
			[ $kind, $slug ] = [ $asset[ 'kind' ], $asset[ 'slug' ] ];
			$response = $this->client->fetch( $kind, $slug, $this->schema );
			if ( $response->type === FeedResponse::TRANSPORT ) {
				// Asked about last next time, so one asset that always fails can't hold up the rest.
				$recorded = $state->asset( $kind, $slug );
				$state->record( $kind, $slug, $now, false, $recorded[ 'sha256' ] ?? null, $recorded[ 'error' ] ?? null );
				$state->unreachableSince ??= $now;
				break;
			}
			$state->unreachableSince = null;
			if ( $response->type === FeedResponse::SCHEMA_RETIRED ) {
				// Every other request would be answered the same way.
				$retired = true;
				break;
			}
			$state->schemaRetiredAt = null;
			$this->apply( $kind, $slug, $response, $state, $now );
		}
		if ( $retired ) {
			$state->schemaRetiredAt ??= $now;
		}
	}

	private function apply( string $kind, string $slug, FeedResponse $response, FeedState $state, int $now ) :void {
		$recorded = $state->asset( $kind, $slug );
		// Only a SHA-256 this site verified is recorded, so a stored file that changed on disk is checked again.
		$verifiedSha = $recorded[ 'sha256' ] ?? null;
		$stored = $this->store->read( $kind, $slug );
		// A published slug always keeps a file, so "none" beside a stored file is kept but isn't a refresh.
		if ( $response->type === FeedResponse::NONE ) {
			$state->record( $kind, $slug, $now, $stored === null, $verifiedSha, null );
			return;
		}
		if ( $response->type !== FeedResponse::FILE ) {
			$state->record( $kind, $slug, $now, false, $verifiedSha, $recorded[ 'error' ] ?? null );
			return;
		}
		$bytes = (string)$response->bytes;
		if ( $stored !== null && $bytes === $stored && $verifiedSha === \hash( 'sha256', $stored ) ) {
			$state->record( $kind, $slug, $now, true, $verifiedSha, null );
			return;
		}
		try {
			$file = $this->verifier->verify( $bytes, $kind, $slug, $this->schema, $now*1000 );
		}
		catch ( RuleFileRefused $e ) {
			$state->record( $kind, $slug, $now, false, $verifiedSha, 'the delivered file was refused ('.$e->reason.')' );
			return;
		}
		$current = $this->verifiedStored( $kind, $slug, $stored, $now );
		if ( $current !== null && $file->compareOrdering( $current ) < 0 ) {
			$state->record( $kind, $slug, $now, false, $current->sha256(), null );
			return;
		}
		if ( !$this->store->write( $kind, $slug, $bytes ) ) {
			$state->record( $kind, $slug, $now, false, $verifiedSha, 'the delivered file could not be saved' );
			return;
		}
		$state->record( $kind, $slug, $now, true, $file->sha256(), null );
	}

	/**
	 * The stored file, when it still verifies under the installed schema, to compare orderings with.
	 */
	private function verifiedStored( string $kind, string $slug, ?string $stored, int $now ) :?VerifiedRuleFile {
		if ( $stored === null ) {
			return null;
		}
		try {
			return $this->verifier->verify( $stored, $kind, $slug, $this->schema, $now*1000 );
		}
		catch ( RuleFileRefused $e ) {
			return null;
		}
	}

	/**
	 * @param array<string, array{kind: string, slug: string}> $assets
	 * @return array{kind: string, slug: string}[]
	 */
	private function order( array $assets, FeedState $state ) :array {
		$stored = $this->store->assets();
		$valid = \array_filter( $assets, fn( array $asset ) :bool => \in_array( $asset[ 'kind' ], [ 'plugin', 'theme' ], true )
			&& \preg_match( RuleFileVerifier::SLUG_PATTERN, $asset[ 'slug' ] ) === 1 );
		$rank = fn( string $key ) :array => [
			isset( $stored[ $key ] ) ? 0 : ( isset( $state->assets[ $key ] ) ? 2 : 1 ),
			$state->assets[ $key ][ 'fetched_at' ] ?? 0,
			$key,
		];
		\uksort( $valid, fn( string $a, string $b ) :int => $rank( $a ) <=> $rank( $b ) );
		return \array_values( $valid );
	}
}
