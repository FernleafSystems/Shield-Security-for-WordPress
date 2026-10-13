<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching;

/**
 * Decides whether one virtual patching rule matches a request.
 *
 * The early pass loads this file before WordPress and without Composer, so it must stay self-contained:
 * no other classes, no WordPress or global functions, no output and no translations.
 *
 * A rule is ['id' => string, 'advisories' => string[], 'conditions' => array], where 'conditions' is the
 * Candidate v2 matcher condition list: an outer AND of leaves and at most one 'or' group of leaves, with
 * each leaf optionally negated.
 *
 * Facts are plain values describing the request:
 * - method:         REQUEST_METHOD as sent, or at REST dispatch the method WordPress dispatches
 * - path:           see pathFromRequestUri()
 * - script:         see scriptFromServer()
 * - query, form:    unslashed $_GET and $_POST
 * - active_plugins: plugin files active on the site, including network-active plugins
 * - active_themes:  the active theme's stylesheet and template
 * - is_admin:       WordPress is serving an administration request
 * - user_can:       callable( string $capability ) :bool for the current user
 * - rest_route:     the route WordPress resolved for this REST dispatch
 * - rest_has_param: callable( string $name ) :bool against the dispatched REST request
 */
final class RuleEvaluator {

	public const MATCH = 'match';
	public const NO_MATCH = 'no_match';
	/**
	 * A regular expression failed to compile or hit a PCRE limit. Callers treat this as a match.
	 */
	public const PATTERN_FAILURE = 'pattern_failure';

	/**
	 * Facts available before WordPress loads. A rule is early-capable when every fact it requires is in this list.
	 */
	public const EARLY_FACTS = [
		'method',
		'path',
		'script',
		'query',
		'form',
		'active_plugins',
		'active_themes',
	];

	private const PCRE_SETTINGS = [
		'pcre.backtrack_limit' => '10000',
		'pcre.recursion_limit' => '1000',
		'pcre.jit'             => '0',
	];

	/**
	 * For each condition kind: the string fields it needs, and the facts it reads. Parameter conditions read the
	 * query, the form or both, as their source says.
	 */
	public const KINDS = [
		'method'                => [ 'fields' => [ 'value' ], 'facts' => [ 'method' ] ],
		'path'                  => [ 'fields' => [ 'operator', 'value' ], 'facts' => [ 'path' ] ],
		'script'                => [ 'fields' => [ 'operator', 'value' ], 'facts' => [ 'script' ] ],
		'parameter_value'       => [ 'fields' => [ 'source', 'name', 'operator', 'value' ], 'facts' => [] ],
		'parameter_exists'      => [ 'fields' => [ 'source', 'name' ], 'facts' => [] ],
		'user_capability'       => [ 'fields' => [ 'capability' ], 'facts' => [ 'user_can' ] ],
		'plugin_active'         => [ 'fields' => [ 'pluginFile' ], 'facts' => [ 'active_plugins' ] ],
		'theme_active'          => [ 'fields' => [ 'theme' ], 'facts' => [ 'active_themes' ] ],
		'wp_is_admin'           => [ 'fields' => [], 'facts' => [ 'is_admin' ] ],
		'rest_route'            => [ 'fields' => [ 'namespace', 'operator', 'value' ], 'facts' => [ 'rest_route' ] ],
		'rest_parameter_exists' => [ 'fields' => [ 'name' ], 'facts' => [ 'rest_has_param' ] ],
	];

	public const OPERATORS = [ 'equals', 'equals_i', 'contains', 'contains_i', 'regex' ];

	private ?array $patternFailure = null;

	/**
	 * Every leaf is checked here before any is evaluated, so a malformed rule is rejected with an exception and
	 * never reaches a PHP warning.
	 * @return string[]
	 * @throws \InvalidArgumentException
	 */
	public function requiredFacts( array $rule ) :array {
		$facts = [];
		foreach ( $this->leaves( $rule ) as $leaf ) {
			$this->checkLeaf( $leaf );
			$kind = $leaf[ 'kind' ];
			if ( $kind === 'parameter_exists' || $kind === 'parameter_value' ) {
				$sourceFacts = $leaf[ 'source' ] === 'either' ? [ 'query', 'form' ] : [ $leaf[ 'source' ] ];
			}
			else {
				$sourceFacts = self::KINDS[ $kind ][ 'facts' ];
			}
			foreach ( $sourceFacts as $fact ) {
				$facts[ $fact ] = true;
			}
		}
		return \array_keys( $facts );
	}

	/**
	 * Leaves are evaluated in order and stop as soon as the outcome is known, as in the assessment lab, so a
	 * pattern failure is only reported when the failing leaf is reached.
	 * @throws \InvalidArgumentException
	 */
	public function evaluate( array $rule, array $facts ) :string {
		$this->patternFailure = null;
		$missing = \array_diff( $this->requiredFacts( $rule ), \array_keys( $facts ) );
		if ( !empty( $missing ) ) {
			throw new \InvalidArgumentException( 'Missing request facts: '.\implode( ', ', $missing ) );
		}

		foreach ( $rule[ 'conditions' ] as $node ) {
			$result = $this->node( $node, $facts );
			if ( $result === null ) {
				return self::PATTERN_FAILURE;
			}
			if ( !$result ) {
				return self::NO_MATCH;
			}
		}
		return self::MATCH;
	}

	public function isEarlyCapable( array $rule ) :bool {
		return empty( \array_diff( $this->requiredFacts( $rule ), self::EARLY_FACTS ) );
	}

	/**
	 * The request facts every pass derives the same way. Query and form values must already be unslashed.
	 */
	public static function requestFacts( array $server, array $query, array $form ) :array {
		$method = $server[ 'REQUEST_METHOD' ] ?? '';
		$uri = $server[ 'REQUEST_URI' ] ?? '';
		return [
			'method' => \is_string( $method ) ? $method : '',
			'path'   => self::pathFromRequestUri( \is_string( $uri ) ? $uri : '' ),
			'script' => self::scriptFromServer( $server ),
			'query'  => $query,
			'form'   => $form,
		];
	}

	/**
	 * The request path: the absolute-form scheme and authority removed, everything from the first "?" removed,
	 * percent-decoded once, and repeated slashes collapsed, so that equivalent requests for the same resource
	 * give the same path. An empty path is "/".
	 */
	public static function pathFromRequestUri( string $requestUri ) :string {
		$path = \explode( '?', $requestUri, 2 )[ 0 ];
		if ( \preg_match( '#^[a-z][a-z0-9+.-]*://[^/]*#i', $path, $matches ) === 1 ) {
			$path = \substr( $path, \strlen( $matches[ 0 ] ) );
		}
		$path = (string)\preg_replace( '#/{2,}#', '/', \rawurldecode( $path ) );
		return $path === '' ? '/' : $path;
	}

	/**
	 * The basename of the executing script: the first non-empty of SCRIPT_NAME, SCRIPT_FILENAME and PHP_SELF.
	 */
	public static function scriptFromServer( array $server ) :string {
		foreach ( [ 'SCRIPT_NAME', 'SCRIPT_FILENAME', 'PHP_SELF' ] as $key ) {
			$value = $server[ $key ] ?? '';
			if ( \is_string( $value ) && $value !== '' ) {
				return \basename( $value );
			}
		}
		return '';
	}

	/**
	 * @return bool|null null when a pattern failed
	 */
	private function node( array $node, array $facts ) :?bool {
		if ( ( $node[ 'kind' ] ?? null ) !== 'or' ) {
			return $this->leaf( $node, $facts );
		}
		foreach ( $node[ 'conditions' ] as $branch ) {
			$result = $this->leaf( $branch, $facts );
			if ( $result !== false ) {
				return $result;
			}
		}
		return false;
	}

	private function leaf( array $leaf, array $facts ) :?bool {
		$result = $this->leafResult( $leaf, $facts );
		return ( $result === null || empty( $leaf[ 'negate' ] ) ) ? $result : !$result;
	}

	private function leafResult( array $leaf, array $facts ) :?bool {
		switch ( $leaf[ 'kind' ] ) {
			case 'method':
				return $facts[ 'method' ] === $leaf[ 'value' ];
			case 'path':
			case 'script':
				return $this->compare( (string)$facts[ $leaf[ 'kind' ] ], $leaf );
			case 'parameter_exists':
				$name = (string)$leaf[ 'name' ];
				if ( $leaf[ 'source' ] === 'either' ) {
					return \array_key_exists( $name, $facts[ 'query' ] ) || \array_key_exists( $name, $facts[ 'form' ] );
				}
				return \array_key_exists( $name, $facts[ $leaf[ 'source' ] ] );
			case 'parameter_value':
				$value = $facts[ $leaf[ 'source' ] ][ (string)$leaf[ 'name' ] ] ?? null;
				return \is_string( $value ) ? $this->compare( $value, $leaf ) : false;
			case 'user_capability':
				return (bool)( $facts[ 'user_can' ] )( (string)$leaf[ 'capability' ] );
			case 'plugin_active':
				return \in_array( $leaf[ 'pluginFile' ], $facts[ 'active_plugins' ], true );
			case 'theme_active':
				return \in_array( $leaf[ 'theme' ], $facts[ 'active_themes' ], true );
			case 'wp_is_admin':
				return (bool)$facts[ 'is_admin' ];
			case 'rest_route':
				return $this->restRoute( $facts[ 'rest_route' ], $leaf );
			case 'rest_parameter_exists':
				return (bool)( $facts[ 'rest_has_param' ] )( (string)$leaf[ 'name' ] );
			default:
				throw new \InvalidArgumentException( 'Unsupported condition kind.' );
		}
	}

	/**
	 * The route must be in the rule's namespace, bounded by "/" or the end of the route, and the rest of the
	 * route is compared with the rule's value. WordPress matches routes case-insensitively, and its anchored
	 * route patterns also accept one trailing newline, so that newline is removed before comparing.
	 */
	private function restRoute( ?string $route, array $leaf ) :?bool {
		if ( $route === null ) {
			return false;
		}
		if ( \substr( $route, -1 ) === "\n" ) {
			$route = \substr( $route, 0, -1 );
		}
		$prefix = '/'.$leaf[ 'namespace' ];
		$length = \strlen( $prefix );
		if ( \strncasecmp( $route, $prefix, $length ) !== 0 || ( \strlen( $route ) > $length && $route[ $length ] !== '/' ) ) {
			return false;
		}
		return $this->compare( (string)\substr( $route, $length ), $leaf );
	}

	private function compare( string $actual, array $leaf ) :?bool {
		$expected = (string)$leaf[ 'value' ];
		switch ( $leaf[ 'operator' ] ) {
			case 'equals':
				return $actual === $expected;
			case 'equals_i':
				return self::asciiLower( $actual ) === self::asciiLower( $expected );
			case 'contains':
				return $expected === '' || \strpos( $actual, $expected ) !== false;
			case 'contains_i':
				return $expected === '' || \strpos( self::asciiLower( $actual ), self::asciiLower( $expected ) ) !== false;
			case 'regex':
				return $this->regex( $expected, $actual );
			default:
				throw new \InvalidArgumentException( 'Unsupported operator.' );
		}
	}

	/**
	 * PCRE limits are applied only for this match, then restored. Hosts that disable ini_set() keep their own
	 * limits, and a match that exceeds them is still reported as a pattern failure.
	 */
	private function regex( string $pattern, string $subject ) :?bool {
		$previous = [];
		if ( \function_exists( 'ini_set' ) ) {
			foreach ( self::PCRE_SETTINGS as $setting => $value ) {
				$old = @\ini_set( $setting, $value );
				if ( $old !== false ) {
					$previous[ $setting ] = $old;
				}
			}
		}
		$warning = null;
		\set_error_handler( static function ( int $level, string $message ) use ( &$warning ) :bool {
			$warning = $message;
			return true;
		} );
		try {
			$result = \preg_match( $pattern, $subject );
			$error = \preg_last_error();
			$message = \function_exists( 'preg_last_error_msg' ) ? \preg_last_error_msg() : '';
		}
		finally {
			\restore_error_handler();
			foreach ( $previous as $setting => $value ) {
				@\ini_set( $setting, (string)$value );
			}
		}
		if ( $result !== false && $error === \PREG_NO_ERROR ) {
			return $result === 1;
		}
		$this->patternFailure = [
			'compile'  => \is_string( $warning ) && \preg_match( '/Compilation failed|delimiter|Unknown modifier|Empty regular expression/', $warning ) === 1,
			'pcreCode' => $error,
			'message'  => $message,
			'warning'  => $warning,
		];
		return null;
	}

	/**
	 * Why the last evaluate() returned PATTERN_FAILURE: whether the pattern failed to compile, the PCRE error
	 * code and message, and any PHP warning. Null when the last evaluation had no pattern failure.
	 * @return array{compile: bool, pcreCode: int, message: string, warning: ?string}|null
	 */
	public function lastPatternFailure() :?array {
		return $this->patternFailure;
	}

	/**
	 * @return array[]
	 */
	private function leaves( array $rule ) :array {
		$conditions = $rule[ 'conditions' ] ?? null;
		if ( !\is_array( $conditions ) ) {
			throw new \InvalidArgumentException( 'A rule needs a list of conditions.' );
		}
		$leaves = [];
		foreach ( $conditions as $node ) {
			if ( !\is_array( $node ) ) {
				throw new \InvalidArgumentException( 'Invalid condition.' );
			}
			if ( ( $node[ 'kind' ] ?? null ) === 'or' ) {
				if ( !\is_array( $node[ 'conditions' ] ?? null ) || empty( $node[ 'conditions' ] ) ) {
					throw new \InvalidArgumentException( 'An or group needs conditions.' );
				}
				foreach ( $node[ 'conditions' ] as $branch ) {
					if ( !\is_array( $branch ) ) {
						throw new \InvalidArgumentException( 'Invalid condition.' );
					}
					$leaves[] = $branch;
				}
			}
			else {
				$leaves[] = $node;
			}
		}
		return $leaves;
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	private function checkLeaf( array $leaf ) :void {
		$kind = $leaf[ 'kind' ] ?? null;
		if ( !\is_string( $kind ) || !isset( self::KINDS[ $kind ] ) ) {
			throw new \InvalidArgumentException( 'Unsupported condition kind.' );
		}
		if ( \array_key_exists( 'negate', $leaf ) && !\is_bool( $leaf[ 'negate' ] ) ) {
			throw new \InvalidArgumentException( 'Invalid negate flag.' );
		}
		foreach ( self::KINDS[ $kind ][ 'fields' ] as $field ) {
			if ( !\is_string( $leaf[ $field ] ?? null ) ) {
				throw new \InvalidArgumentException( 'Missing or invalid condition field: '.$field );
			}
		}
		if ( isset( $leaf[ 'operator' ] ) && !\in_array( $leaf[ 'operator' ], self::OPERATORS, true ) ) {
			throw new \InvalidArgumentException( 'Unsupported operator.' );
		}
		if ( isset( $leaf[ 'source' ] )
			 && !\in_array( $leaf[ 'source' ], $kind === 'parameter_exists' ? [ 'query', 'form', 'either' ] : [ 'query', 'form' ], true ) ) {
			throw new \InvalidArgumentException( 'Unsupported parameter source.' );
		}
	}

	private static function asciiLower( string $value ) :string {
		return \strtr( $value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz' );
	}
}
