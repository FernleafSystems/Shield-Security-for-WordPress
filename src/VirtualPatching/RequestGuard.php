<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching;

use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset\RulesetFile;

/**
 * Checks virtual patching rules inside WordPress, each at the earliest point where its conditions can be decided:
 * - load: as soon as Shield's plugin file is included, for request, plugin, theme and admin-area conditions;
 * - user: at 'init', once WordPress has set up the current user, for capability conditions;
 * - rest: at 'rest_pre_dispatch', on every dispatch, for REST route and parameter conditions.
 *
 * Shield's plugin file includes this before Composer loads, so it only uses the self-contained virtual patching
 * classes and WordPress functions. It never throws: a failure leaves the request to WordPress as before.
 */
final class RequestGuard {

	public const OPTION_RULESET_PATH = 'icwp_wpsf_virtual_patching_ruleset';

	public const POINT_LOAD = 'load';
	public const POINT_USER = 'user';
	public const POINT_REST = 'rest';

	private static ?RequestGuard $instance = null;

	private array $ruleset;

	private RuleEvaluator $evaluator;

	/**
	 * @var callable
	 */
	private $respond;

	/**
	 * @var array<string, array[]>
	 */
	private array $rulesByPoint = [];

	/**
	 * @param callable|null $respond receives the blocked rule and point; by default sends a 403 and exits
	 */
	public function __construct( array $ruleset, ?callable $respond = null ) {
		$this->ruleset = $ruleset;
		$this->evaluator = new RuleEvaluator();
		$this->respond = $respond ?? [ $this, 'sendForbidden' ];
	}

	public static function boot() :void {
		try {
			if ( self::$instance !== null || \PHP_SAPI === 'cli' || !\function_exists( 'get_option' ) || !\function_exists( 'add_action' )
				 || ( \function_exists( 'is_multisite' ) && is_multisite() ) ) {
				return;
			}
			$path = get_option( self::OPTION_RULESET_PATH );
			$ruleset = \is_string( $path ) && $path !== '' ? RulesetFile::read( $path ) : null;
			if ( $ruleset !== null && !empty( $ruleset[ 'rules' ] ) ) {
				self::$instance = new self( $ruleset );
				self::$instance->start();
			}
		}
		catch ( \Throwable $e ) {
		}
	}

	public function start() :void {
		if ( IpBypass::isBypassed( $this->ruleset, $this->server() ) ) {
			return;
		}

		$earlyRan = \defined( 'SHIELD_EARLY_RAN' ) && \constant( 'SHIELD_EARLY_RAN' ) === ( $this->ruleset[ 'hash' ] ?? null );
		foreach ( $this->ruleset[ 'rules' ] as $rule ) {
			try {
				if ( !( $earlyRan && $this->evaluator->isEarlyCapable( $rule ) ) ) {
					$this->rulesByPoint[ $this->pointFor( $rule ) ][] = $rule;
				}
			}
			catch ( \Throwable $e ) {
			}
		}

		if ( !empty( $this->rulesByPoint[ self::POINT_LOAD ] ) ) {
			$this->runLoadRules();
		}
		if ( !empty( $this->rulesByPoint[ self::POINT_USER ] ) ) {
			did_action( 'init' ) ? $this->runUserRules() : add_action( 'init', [ $this, 'runUserRules' ], \PHP_INT_MIN, 0 );
		}
		if ( !empty( $this->rulesByPoint[ self::POINT_REST ] ) ) {
			add_filter( 'rest_pre_dispatch', [ $this, 'checkRestDispatch' ], \PHP_INT_MIN, 3 );
		}
	}

	public function runLoadRules() :void {
		$this->run( self::POINT_LOAD, $this->requestFacts() );
	}

	public function runUserRules() :void {
		$this->run( self::POINT_USER, \array_merge( $this->requestFacts(), [
			'user_can' => fn( string $capability ) :bool => \function_exists( 'current_user_can' ) && current_user_can( $capability ),
		] ) );
	}

	/**
	 * @param mixed $result
	 * @param mixed $server
	 * @param mixed $request
	 * @return mixed
	 */
	public function checkRestDispatch( $result, $server = null, $request = null ) {
		if ( !$request instanceof \WP_REST_Request ) {
			return $result;
		}
		$blocked = $this->run( self::POINT_REST, \array_merge( $this->requestFacts(), [
			'rest_route'     => $request->get_route(),
			'rest_has_param' => fn( string $name ) :bool => $request->has_param( $name ),
		] ) );
		return $blocked === null ? $result : $blocked;
	}

	/**
	 * @return mixed whatever the responder returns for a block, or null when nothing matched
	 */
	private function run( string $point, array $facts ) {
		foreach ( $this->rulesByPoint[ $point ] ?? [] as $rule ) {
			try {
				$verdict = $this->evaluator->evaluate( $rule, $facts );
			}
			catch ( \Throwable $e ) {
				continue;
			}
			if ( $verdict !== RuleEvaluator::NO_MATCH ) {
				$this->log( $rule, $point, $verdict, $facts );
				return ( $this->respond )( $rule, $point );
			}
		}
		return null;
	}

	private function pointFor( array $rule ) :string {
		$facts = $this->evaluator->requiredFacts( $rule );
		if ( \in_array( 'rest_route', $facts, true ) || \in_array( 'rest_has_param', $facts, true ) ) {
			return self::POINT_REST;
		}
		return \in_array( 'user_can', $facts, true ) ? self::POINT_USER : self::POINT_LOAD;
	}

	private function requestFacts() :array {
		$unslash = $this->isSlashed();
		$facts = RuleEvaluator::requestFacts(
			$this->server(),
			$unslash ? $this->unslash( $_GET ) : $_GET,
			$unslash ? $this->unslash( $_POST ) : $_POST
		);
		$facts[ 'active_plugins' ] = \is_array( $this->ruleset[ 'active_plugins' ] ?? null ) ? $this->ruleset[ 'active_plugins' ] : [];
		$facts[ 'active_themes' ] = \is_array( $this->ruleset[ 'active_themes' ] ?? null ) ? $this->ruleset[ 'active_themes' ] : [];
		$facts[ 'is_admin' ] = ( \function_exists( 'is_admin' ) && is_admin() )
							   || ( \function_exists( 'is_network_admin' ) && is_network_admin() );
		return $facts;
	}

	private function server() :array {
		return $this->isSlashed() ? $this->unslash( $_SERVER ) : $_SERVER;
	}

	/**
	 * wp_magic_quotes() adds slashes to the request superglobals just before 'sanitize_comment_cookies'.
	 */
	private function isSlashed() :bool {
		return \function_exists( 'did_action' ) && did_action( 'sanitize_comment_cookies' ) > 0;
	}

	private function unslash( array $values ) :array {
		if ( \function_exists( 'wp_unslash' ) ) {
			return wp_unslash( $values );
		}
		return \array_map( fn( $value ) => \is_array( $value ) ? $this->unslash( $value ) : ( \is_string( $value ) ? \stripslashes( $value ) : $value ), $values );
	}

	private function log( array $rule, string $point, string $verdict, array $facts ) :void {
		$path = $this->ruleset[ 'events' ] ?? null;
		if ( !\is_string( $path ) || $path === '' ) {
			return;
		}
		$server = $this->server();
		$remote = $server[ 'REMOTE_ADDR' ] ?? '';
		BlockLog::append( $path, [
			'ts'         => \time(),
			'rule'       => (string)( $rule[ 'id' ] ?? '' ),
			'advisories' => \is_array( $rule[ 'advisories' ] ?? null ) ? $rule[ 'advisories' ] : [],
			'ip'         => IpBypass::requestIp( $this->ruleset[ 'bypass' ][ 'ip_source' ] ?? null, $server ) ?? ( \is_string( $remote ) ? $remote : '' ),
			'method'     => $facts[ 'method' ],
			'path'       => $facts[ 'path' ],
			'pass'       => $point,
			'verdict'    => $verdict,
		] );
	}

	/**
	 * @return \WP_Error|never
	 */
	public function sendForbidden( array $rule, string $point ) {
		if ( $point === self::POINT_REST && \class_exists( '\WP_Error' ) ) {
			return new \WP_Error( 'shield_virtual_patch', 'Forbidden', [ 'status' => 403 ] );
		}
		if ( !\headers_sent() ) {
			\function_exists( 'status_header' ) ? status_header( 403 ) : \http_response_code( 403 );
			if ( \function_exists( 'nocache_headers' ) ) {
				nocache_headers();
			}
			\header( 'Content-Type: text/plain; charset=utf-8' );
		}
		if ( !\defined( 'DONOTCACHEPAGE' ) ) {
			\define( 'DONOTCACHEPAGE', true );
		}
		echo 'Forbidden';
		exit;
	}
}
