<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery;

use FernleafSystems\Orle\VirtualPatchingRules\PatchValidator;

/**
 * The rules in the stored rule files, each checked before the ruleset is built. Loading runs on every export, so
 * a stored file's signature isn't checked again while its SHA-256 is the one this site verified; any other file is
 * verified in full. A file signed by a key no longer trusted, or of another rule schema than the installed one, is
 * discarded and fetched again; one that fails verification is also reported.
 *
 * Each rule must carry its id, title, link and copyright notices, a mode of log or block, and conditions the rule
 * package accepts for the file's kind. A rule that doesn't is left out and reported with the reason.
 */
class PublishedRules {

	private RuleFileStore $store;

	private RuleFileVerifier $verifier;

	private TrustedKeys $keys;

	private PatchValidator $validator;

	private int $schema;

	public function __construct( RuleFileStore $store, RuleFileVerifier $verifier, TrustedKeys $keys, int $schema, ?PatchValidator $validator = null ) {
		$this->store = $store;
		$this->verifier = $verifier;
		$this->keys = $keys;
		$this->schema = $schema;
		$this->validator = $validator ?? new PatchValidator();
	}

	/**
	 * @return array{rules: array[], rejected: array<string, string>} each rule as { kind, slug, mode, rule } with
	 *                                                                 the rule decoded to arrays
	 */
	public function load( FeedState $state, int $now ) :array {
		$rules = [];
		$rejected = [];
		foreach ( $this->store->assets() as $asset ) {
			[ $kind, $slug ] = [ $asset[ 'kind' ], $asset[ 'slug' ] ];
			$payload = $this->payload( $kind, $slug, $state, $now );
			foreach ( $payload === null ? [] : \array_values( $payload->rules ) as $index => $rule ) {
				$id = $rule instanceof \stdClass && \is_string( $rule->id ?? null ) && \trim( $rule->id ) !== ''
					? $rule->id : $kind.'/'.$slug.'#'.$index;
				$problems = $rule instanceof \stdClass ? $this->problems( $rule, $kind ) : [ 'a rule must be an object' ];
				if ( !empty( $problems ) ) {
					$rejected[ $id ] = \implode( '; ', $problems );
					continue;
				}
				$rules[] = [
					'kind' => $kind,
					'slug' => $slug,
					'mode' => $rule->mode,
					'rule' => \json_decode( (string)\json_encode( $rule ), true ),
				];
			}
		}
		return [ 'rules' => $rules, 'rejected' => $rejected ];
	}

	/**
	 * The stored file's payload once it passes the load checks, or null when it is discarded.
	 */
	private function payload( string $kind, string $slug, FeedState $state, int $now ) :?\stdClass {
		$bytes = $this->store->read( $kind, $slug );
		if ( $bytes === null ) {
			return null;
		}
		$keyId = RuleFileVerifier::keyIdOf( $bytes );
		if ( $keyId === null ) {
			$this->discard( $kind, $slug, $state, 'the stored file could not be read' );
			return null;
		}
		if ( !$this->keys->isTrusted( $keyId ) ) {
			$this->discard( $kind, $slug, $state, null );
			return null;
		}
		if ( \hash( 'sha256', $bytes ) === ( $state->asset( $kind, $slug )[ 'sha256' ] ?? null ) ) {
			$envelope = \json_decode( $bytes );
			$decoded = Base64::decode( (string)( $envelope->payload ?? '' ) );
			$payload = $decoded === null ? null : \json_decode( $decoded );
			if ( $payload instanceof \stdClass && \is_array( $payload->rules ?? null ) && ( $payload->schema ?? null ) === $this->schema ) {
				return $payload;
			}
		}
		try {
			$file = $this->verifier->verify( $bytes, $kind, $slug, $this->schema, $now*1000 );
		}
		catch ( RuleFileRefused $e ) {
			// After a Shield update that changes the schema, the old schema's file is replaced, not reported.
			$this->discard( $kind, $slug, $state, $e->reason === 'schema_mismatch' ? null : 'the stored file failed verification ('.$e->reason.')' );
			return null;
		}
		$recorded = $state->asset( $kind, $slug );
		$state->assets[ $kind.'/'.$slug ] = \array_merge( $recorded ?? [ 'first_at' => $now, 'fetched_at' => 0, 'refreshed_at' => null, 'error' => null ], [ 'sha256' => $file->sha256() ] );
		return $file->payload;
	}

	/**
	 * Removes the stored file so the next fetch asks for it again as if new.
	 */
	private function discard( string $kind, string $slug, FeedState $state, ?string $problem ) :void {
		$this->store->delete( $kind, $slug );
		if ( $problem === null ) {
			$state->forget( $kind, $slug );
		}
		else {
			$recorded = $state->asset( $kind, $slug );
			$state->assets[ $kind.'/'.$slug ] = [
				'first_at'     => $recorded[ 'first_at' ] ?? 0,
				'fetched_at'   => 0,
				'refreshed_at' => $recorded[ 'refreshed_at' ] ?? null,
				'sha256'       => null,
				'error'        => $problem,
			];
		}
	}

	/**
	 * @return string[]
	 */
	private function problems( \stdClass $rule, string $kind ) :array {
		$problems = [];
		foreach ( [ 'id', 'title', 'url' ] as $member ) {
			if ( !\is_string( $rule->{$member} ?? null ) || \trim( $rule->{$member} ) === '' ) {
				$problems[] = $member.' is required';
			}
		}
		if ( !self::notices( $rule->copyrights ?? null ) ) {
			$problems[] = 'copyrights must be a non-empty list of notices';
		}
		if ( !\in_array( $rule->mode ?? null, [ 'log', 'block' ], true ) ) {
			$problems[] = 'mode must be log or block';
		}
		return \array_merge( $problems, $this->validator->validateRule( $rule, $kind ) );
	}

	/**
	 * Each notice has its text and licence, and a licence URL or null, which Shield shows wherever it shows a title.
	 */
	private static function notices( $copyrights ) :bool {
		if ( !\is_array( $copyrights ) || empty( $copyrights ) ) {
			return false;
		}
		foreach ( $copyrights as $notice ) {
			if ( !$notice instanceof \stdClass
				 || !\is_string( $notice->notice ?? null ) || \trim( $notice->notice ) === ''
				 || !\is_string( $notice->license ?? null ) || \trim( $notice->license ) === ''
				 || !\property_exists( $notice, 'licenseUrl' )
				 || !( $notice->licenseUrl === null || ( \is_string( $notice->licenseUrl ) && \preg_match( '#^https?://\S+$#D', $notice->licenseUrl ) ) ) ) {
				return false;
			}
		}
		return true;
	}
}
