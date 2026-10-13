<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset;

/**
 * Checks a virtual patch (a Factory Candidate, contract version 2) before it is used. The checks mirror the
 * Factory's own contract so that a patch Shield accepts is one the Factory could have produced and tested.
 */
class PatchValidator {

	private const MAX_LEAVES = 8;
	private const MAX_VALUE_LENGTH = 1024;
	private const MAX_FIELD_LENGTH = 256;

	private const METHODS = [ 'GET', 'POST', 'HEAD', 'OPTIONS', 'PUT', 'PATCH', 'DELETE' ];
	private const STRING_OPERATORS = [ 'equals', 'equals_i', 'contains', 'contains_i', 'regex' ];
	private const REST_ROUTE_OPERATORS = [ 'equals_i', 'regex' ];

	private const FIELDS = [
		'parameter_exists'      => [ 'source', 'name' ],
		'rest_parameter_exists' => [ 'name' ],
		'parameter_value'       => [ 'source', 'name', 'operator', 'value' ],
		'path'                  => [ 'operator', 'value' ],
		'script'                => [ 'operator', 'value' ],
		'user_capability'       => [ 'capability' ],
		'plugin_active'         => [ 'pluginFile' ],
		'theme_active'          => [ 'theme' ],
		'wp_is_admin'           => [],
		'method'                => [ 'value' ],
		'rest_route'            => [ 'namespace', 'operator', 'value' ],
	];

	private const REQUEST_CONDITIONS = [
		'parameter_exists',
		'rest_parameter_exists',
		'parameter_value',
		'path',
		'script',
		'rest_route',
		'wp_is_admin',
	];

	/**
	 * @return string[] problems found; empty when the patch is valid
	 */
	public function validate( array $patch ) :array {
		$problems = [];

		if ( ( $patch[ 'contractVersion' ] ?? null ) !== 2 ) {
			$problems[] = 'contractVersion must be 2';
		}
		if ( !$this->isNonBlankString( $patch[ 'candidateRevisionId' ] ?? null ) ) {
			$problems[] = 'candidateRevisionId is required';
		}

		$target = $patch[ 'targetExtension' ] ?? null;
		if ( !\is_array( $target )
			 || !\in_array( $target[ 'kind' ] ?? null, [ 'plugin', 'theme' ], true )
			 || !$this->isNonBlankString( $target[ 'slug' ] ?? null )
			 || !\is_array( $target[ 'affectedVersionRanges' ] ?? null )
			 || empty( $target[ 'affectedVersionRanges' ] ) ) {
			$problems[] = 'targetExtension needs kind, slug and affectedVersionRanges';
		}
		else {
			foreach ( $target[ 'affectedVersionRanges' ] as $range ) {
				if ( !\is_array( $range )
					 || !$this->isNonBlankString( $range[ 'firstAffectedVersion' ] ?? null )
					 || !$this->isNonBlankString( $range[ 'lastAffectedVersion' ] ?? null )
					 || ( isset( $range[ 'firstKnownFixedVersion' ] ) && !$this->isNonBlankString( $range[ 'firstKnownFixedVersion' ] ) ) ) {
					$problems[] = 'each affected version range needs firstAffectedVersion and lastAffectedVersion';
					break;
				}
			}
		}

		$ids = $patch[ 'publicVulnerabilityIds' ] ?? null;
		if ( !\is_array( $ids ) || empty( $ids ) ) {
			$problems[] = 'publicVulnerabilityIds is required';
		}
		else {
			foreach ( $ids as $id ) {
				if ( !\is_array( $id ) || !$this->isNonBlankString( $id[ 'identifier' ] ?? null ) ) {
					$problems[] = 'each public vulnerability ID needs an identifier';
					break;
				}
			}
		}

		$virtualPatch = $patch[ 'virtualPatch' ] ?? null;
		if ( !\is_array( $virtualPatch ) || ( $virtualPatch[ 'action' ] ?? null ) !== 'block' ) {
			$problems[] = 'virtualPatch.action must be block';
		}
		$conditions = $virtualPatch[ 'matcher' ][ 'conditions' ] ?? null;
		if ( !\is_array( $conditions ) || empty( $conditions ) || !\array_is_list( $conditions ) ) {
			$problems[] = 'virtualPatch.matcher.conditions must be a non-empty list';
		}
		else {
			$problems = \array_merge( $problems, $this->validateConditions( $conditions, $target[ 'kind' ] ?? null ) );
		}

		return $problems;
	}

	/**
	 * @return string[]
	 */
	private function validateConditions( array $conditions, $targetKind ) :array {
		$problems = [];
		$seen = [];
		$leafCount = 0;
		$groupCount = 0;
		$restRoutes = 0;
		$restParameters = 0;
		$userState = false;
		$positiveOuter = false;
		$groupAllPositive = false;

		$checkLeaf = function ( $leaf, string $at ) use ( &$problems, &$seen, &$leafCount, &$restParameters, &$userState, $targetKind ) :?array {
			$leafCount++;
			$leafProblems = $this->validateLeaf( $leaf );
			if ( !empty( $leafProblems ) ) {
				foreach ( $leafProblems as $problem ) {
					$problems[] = $at.': '.$problem;
				}
				return null;
			}
			$key = $leaf;
			unset( $key[ 'negate' ] );
			\ksort( $key );
			$key = \serialize( $key );
			if ( isset( $seen[ $key ] ) ) {
				$problems[] = $at.': duplicate condition';
			}
			$seen[ $key ] = true;
			if ( $leaf[ 'kind' ] === 'theme_active' && $targetKind !== 'theme' ) {
				$problems[] = $at.': theme_active is only valid in a theme patch';
			}
			if ( \in_array( $leaf[ 'kind' ], [ 'user_capability', 'wp_is_admin' ], true ) ) {
				$userState = true;
			}
			if ( $leaf[ 'kind' ] === 'rest_parameter_exists' ) {
				$restParameters++;
			}
			return $leaf;
		};

		foreach ( $conditions as $index => $node ) {
			$at = 'conditions['.$index.']';
			if ( \is_array( $node ) && ( $node[ 'kind' ] ?? null ) === 'or' ) {
				if ( ++$groupCount > 1 ) {
					$problems[] = $at.': at most one or group is allowed';
				}
				$branches = $node[ 'conditions' ] ?? null;
				if ( \count( $node ) !== 2 || !\is_array( $branches ) || !\array_is_list( $branches ) || \count( $branches ) < 2 ) {
					$problems[] = $at.': an or group needs kind and at least two conditions';
					continue;
				}
				$groupAllPositive = true;
				foreach ( $branches as $branchIndex => $branch ) {
					$leaf = $checkLeaf( $branch, $at.'.conditions['.$branchIndex.']' );
					if ( $leaf === null ) {
						$groupAllPositive = false;
						continue;
					}
					if ( $leaf[ 'kind' ] === 'rest_route' ) {
						$problems[] = $at.': rest_route cannot be inside an or group';
					}
					$groupAllPositive = $groupAllPositive && $this->isPositiveRequestCondition( $leaf );
				}
				continue;
			}

			$leaf = $checkLeaf( $node, $at );
			if ( $leaf === null ) {
				continue;
			}
			if ( $leaf[ 'kind' ] === 'rest_route' ) {
				if ( $leaf[ 'negate' ] ) {
					$problems[] = $at.': rest_route cannot be negated';
				}
				elseif ( ++$restRoutes > 1 ) {
					$problems[] = $at.': at most one rest_route is allowed';
				}
			}
			$positiveOuter = $positiveOuter || $this->isPositiveRequestCondition( $leaf );
		}

		if ( $leafCount > self::MAX_LEAVES ) {
			$problems[] = 'a patch has at most '.self::MAX_LEAVES.' conditions';
		}
		if ( $restRoutes > 0 && $userState ) {
			$problems[] = 'rest_route cannot be combined with user_capability or wp_is_admin';
		}
		if ( $restParameters > 0 && $restRoutes !== 1 ) {
			$problems[] = 'rest_parameter_exists needs exactly one rest_route';
		}
		if ( !$positiveOuter && !( $groupCount === 1 && $groupAllPositive ) ) {
			$problems[] = 'a patch needs an unnegated request condition';
		}
		return $problems;
	}

	/**
	 * @return string[]
	 */
	private function validateLeaf( $leaf ) :array {
		if ( !\is_array( $leaf ) ) {
			return [ 'a condition must be an object' ];
		}
		$kind = $leaf[ 'kind' ] ?? null;
		if ( !\is_string( $kind ) || !isset( self::FIELDS[ $kind ] ) ) {
			return [ 'unsupported condition kind' ];
		}
		$expected = \array_merge( [ 'kind', 'negate' ], self::FIELDS[ $kind ] );
		$keys = \array_keys( $leaf );
		\sort( $expected );
		\sort( $keys );
		if ( $keys !== $expected ) {
			return [ $kind.' needs exactly: '.\implode( ', ', $expected ) ];
		}
		if ( !\is_bool( $leaf[ 'negate' ] ) ) {
			return [ 'negate must be true or false' ];
		}

		$problems = [];
		foreach ( self::FIELDS[ $kind ] as $field ) {
			$value = $leaf[ $field ];
			if ( !\is_string( $value ) ) {
				$problems[] = $field.' must be a string';
				continue;
			}
			$exact = $field === 'value' && \in_array( $leaf[ 'operator' ] ?? null, [ 'equals', 'equals_i' ], true );
			if ( !$exact && \trim( $value ) === '' ) {
				$problems[] = $field.' must not be blank';
			}
			if ( \mb_strlen( $value, 'UTF-8' ) > ( $field === 'value' ? self::MAX_VALUE_LENGTH : self::MAX_FIELD_LENGTH ) ) {
				$problems[] = $field.' is too long';
			}
		}
		if ( !empty( $problems ) ) {
			return $problems;
		}

		switch ( $kind ) {
			case 'parameter_exists':
				if ( !\in_array( $leaf[ 'source' ], [ 'query', 'form', 'either' ], true ) ) {
					$problems[] = 'source must be query, form or either';
				}
				break;
			case 'parameter_value':
				if ( !\in_array( $leaf[ 'source' ], [ 'query', 'form' ], true ) ) {
					$problems[] = 'source must be query or form';
				}
				break;
			case 'user_capability':
				if ( \preg_match( '/^[a-zA-Z0-9_-]+$/', $leaf[ 'capability' ] ) !== 1 ) {
					$problems[] = 'invalid capability';
				}
				break;
			case 'theme_active':
				if ( \preg_match( '/^[A-Za-z0-9._-]+$/', $leaf[ 'theme' ] ) !== 1 || \in_array( $leaf[ 'theme' ], [ '.', '..' ], true ) ) {
					$problems[] = 'invalid theme directory';
				}
				break;
			case 'plugin_active':
				if ( !$this->isPluginFile( $leaf[ 'pluginFile' ] ) ) {
					$problems[] = 'invalid plugin file';
				}
				break;
			case 'method':
				if ( !\in_array( $leaf[ 'value' ], self::METHODS, true ) ) {
					$problems[] = 'unsupported method';
				}
				break;
			case 'rest_route':
				if ( \preg_match( '#^[A-Za-z0-9_-]+(?:/[A-Za-z0-9._-]+)*$#', $leaf[ 'namespace' ] ) !== 1 ) {
					$problems[] = 'invalid REST namespace';
				}
				break;
			default:
				break;
		}

		if ( isset( $leaf[ 'operator' ] ) ) {
			$operators = $kind === 'rest_route' ? self::REST_ROUTE_OPERATORS : self::STRING_OPERATORS;
			if ( !\in_array( $leaf[ 'operator' ], $operators, true ) ) {
				$problems[] = 'unsupported operator';
			}
			elseif ( $leaf[ 'operator' ] === 'regex' && !$this->isCompilablePattern( $leaf[ 'value' ] ) ) {
				$problems[] = 'regular expression does not compile';
			}
			elseif ( !$this->locationValueFits( $kind, $leaf[ 'operator' ], $leaf[ 'value' ] ) ) {
				$problems[] = 'value can never match this condition';
			}
		}
		return $problems;
	}

	private function isPositiveRequestCondition( array $leaf ) :bool {
		return !$leaf[ 'negate' ] && \in_array( $leaf[ 'kind' ], self::REQUEST_CONDITIONS, true );
	}

	/**
	 * A path always starts with "/", a script basename never contains "/", and a REST route remainder is empty
	 * or starts with "/". REST regexes must be case-insensitive, as WordPress route matching is.
	 */
	private function locationValueFits( string $kind, string $operator, string $value ) :bool {
		switch ( $kind ) {
			case 'rest_route':
				return $operator === 'regex'
					? \strpos( (string)$this->pcreModifiers( $value ), 'i' ) !== false
					: ( $value === '' || \strpos( $value, '/' ) === 0 );
			case 'path':
				return !\in_array( $operator, [ 'equals', 'equals_i' ], true ) || \strpos( $value, '/' ) === 0;
			case 'script':
				$subject = $operator === 'regex' ? ( $this->pcreParts( $value )[ 'body' ] ?? $value ) : $value;
				return \strpos( $subject, '/' ) === false;
			default:
				return true;
		}
	}

	private function pcreModifiers( string $pattern ) :?string {
		return $this->pcreParts( $pattern )[ 'modifiers' ] ?? null;
	}

	/**
	 * @return array{body: string, modifiers: string}|null null when the pattern has no recognisable delimiters
	 */
	private function pcreParts( string $pattern ) :?array {
		$open = $pattern[ 0 ] ?? '';
		$close = [ '(' => ')', '[' => ']', '{' => '}', '<' => '>' ][ $open ] ?? $open;
		$end = $close === '' ? false : \strrpos( $pattern, $close );
		if ( \preg_match( '/^[^\sa-zA-Z0-9\\\\]$/', $open ) !== 1 || $end === false || $end === 0 ) {
			return null;
		}
		$modifiers = \substr( $pattern, $end + 1 );
		return \preg_match( '/^[a-zA-Z]*$/', $modifiers ) === 1
			? [ 'body' => \substr( $pattern, 1, $end - 1 ), 'modifiers' => $modifiers ]
			: null;
	}

	private function isCompilablePattern( string $pattern ) :bool {
		\set_error_handler( static function () :bool {
			return true;
		} );
		try {
			$result = \preg_match( $pattern, '' );
		}
		finally {
			\restore_error_handler();
		}
		return $result !== false && \preg_last_error() === \PREG_NO_ERROR;
	}

	private function isPluginFile( string $file ) :bool {
		if ( \substr( $file, -4 ) !== '.php' || \preg_match( '#[\\\\:\x00-\x1F\x7F]#', $file ) === 1 ) {
			return false;
		}
		foreach ( \explode( '/', $file ) as $part ) {
			if ( $part === '' || $part === '.' || $part === '..' ) {
				return false;
			}
		}
		return true;
	}

	private function isNonBlankString( $value ) :bool {
		return \is_string( $value ) && \trim( $value ) !== '';
	}
}
