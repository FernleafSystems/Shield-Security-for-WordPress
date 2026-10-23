<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset;

use FernleafSystems\Orle\VirtualPatchingRules\PatchValidator;

/**
 * Factory Candidates from a local patches.json, for development parity checks only: each valid Candidate becomes a
 * block rule in the same form as a delivered one. A list of Candidates, or an object with a 'patches' list.
 */
class LocalCandidates {

	private PatchValidator $validator;

	public function __construct( ?PatchValidator $validator = null ) {
		$this->validator = $validator ?? new PatchValidator();
	}

	/**
	 * @return array{rules: array[], rejected: array<string, string>}
	 */
	public function load( string $json ) :array {
		$decoded = \json_decode( $json );
		$patches = $decoded instanceof \stdClass ? ( $decoded->patches ?? null ) : $decoded;
		$rules = [];
		$rejected = [];
		foreach ( \is_array( $patches ) ? \array_values( $patches ) : [] as $index => $patch ) {
			$id = $patch instanceof \stdClass && \is_string( $patch->candidateRevisionId ?? null )
				? $patch->candidateRevisionId : '#'.$index;
			$problems = $patch instanceof \stdClass ? $this->validator->validate( $patch ) : [ 'a patch must be an object' ];
			if ( !empty( $problems ) ) {
				$rejected[ $id ] = \implode( '; ', $problems );
				continue;
			}
			$patch = \json_decode( (string)\json_encode( $patch ), true );
			$rules[] = [
				'kind' => $patch[ 'targetExtension' ][ 'kind' ],
				'slug' => $patch[ 'targetExtension' ][ 'slug' ],
				'mode' => 'block',
				'rule' => [
					'id'                     => $id,
					'publicVulnerabilityIds' => $patch[ 'publicVulnerabilityIds' ],
					'affectedVersionRanges'  => $patch[ 'targetExtension' ][ 'affectedVersionRanges' ],
					'conditions'             => $patch[ 'virtualPatch' ][ 'matcher' ][ 'conditions' ],
				],
			];
		}
		return [ 'rules' => $rules, 'rejected' => $rejected ];
	}
}
