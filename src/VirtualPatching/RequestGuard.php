<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching;

use FernleafSystems\Orle\VirtualPatchingRules\{
	EvaluationPoint,
	RuleEvaluator,
	WordPressFacts
};
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset\RulesetFile;

/**
 * Checks virtual patching rules inside WordPress, each at the evaluation point the rule package gives it:
 * - request: as soon as Shield's plugin file is included;
 * - user: at 'init', once WordPress has set up the current user. The user is only looked up when the rule's
 *   earlier conditions already match;
 * - rest: at 'rest_pre_dispatch', on every dispatch.
 *
 * Shield's plugin file includes this before Composer loads, so it only uses the self-contained virtual patching
 * classes, the rule package's classes it requires by path, and WordPress functions. It never throws: a failure
 * leaves the request to WordPress as before.
 */
final class RequestGuard {

	public const OPTION_RULESET_PATH = 'icwp_wpsf_virtual_patching_ruleset';

	private static ?RequestGuard $instance = null;

	private array $ruleset;

	private RuleEvaluator $evaluator;

	/**
	 * @var callable
	 */
	private $respond;

	/**
	 * @var array<string, array[]> rules by EvaluationPoint value
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
			if ( self::$instance === null && \PHP_SAPI !== 'cli' ) {
				self::$instance = self::fromSite();
				if ( self::$instance !== null ) {
					self::$instance->start();
				}
			}
		}
		catch ( \Throwable $e ) {
		}
	}

	/**
	 * A guard for the site's current ruleset, or null when there is nothing to check: on multisite, before
	 * WordPress's options are available, or without a readable ruleset that has rules.
	 */
	public static function fromSite( ?callable $respond = null ) :?self {
		if ( !\function_exists( 'get_option' ) || !\function_exists( 'add_action' )
			 || ( \function_exists( 'is_multisite' ) && is_multisite() ) ) {
			return null;
		}
		$path = get_option( self::OPTION_RULESET_PATH );
		$ruleset = \is_string( $path ) && $path !== '' ? RulesetFile::read( $path ) : null;
		return $ruleset !== null && !empty( $ruleset[ 'rules' ] ) ? new self( $ruleset, $respond ) : null;
	}

	public function start() :void {
		if ( IpBypass::isBypassed( $this->ruleset, WordPressFacts::server() ) ) {
			return;
		}

		$earlyRan = \defined( 'SHIELD_EARLY_RAN' ) && \constant( 'SHIELD_EARLY_RAN' ) === ( $this->ruleset[ 'hash' ] ?? null );
		foreach ( $this->ruleset[ 'rules' ] as $rule ) {
			try {
				if ( !( $earlyRan && $this->evaluator->isEarlyCapable( $rule ) ) ) {
					$this->rulesByPoint[ $this->evaluator->pointFor( $rule )->value ][] = $rule;
				}
			}
			catch ( \Throwable $e ) {
			}
		}

		if ( !empty( $this->rulesByPoint[ EvaluationPoint::Request->value ] ) ) {
			$this->runRequestRules();
		}
		if ( !empty( $this->rulesByPoint[ EvaluationPoint::User->value ] ) ) {
			did_action( 'init' ) ? $this->runUserRules() : add_action( 'init', [ $this, 'runUserRules' ], \PHP_INT_MIN, 0 );
		}
		if ( !empty( $this->rulesByPoint[ EvaluationPoint::Rest->value ] ) ) {
			add_filter( 'rest_pre_dispatch', [ $this, 'checkRestDispatch' ], \PHP_INT_MIN, 3 );
		}
	}

	public function runRequestRules() :void {
		$this->run( EvaluationPoint::Request, $this->requestFacts() );
	}

	public function runUserRules() :void {
		$this->run( EvaluationPoint::User, \array_merge( $this->requestFacts(), WordPressFacts::user() ) );
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
		$blocked = $this->run( EvaluationPoint::Rest, \array_merge( $this->requestFacts(), WordPressFacts::rest( $request ) ) );
		return $blocked === null ? $result : $blocked;
	}

	/**
	 * @return mixed whatever the responder returns for a block, or null when nothing matched
	 */
	private function run( EvaluationPoint $point, array $facts ) {
		foreach ( $this->rulesByPoint[ $point->value ] ?? [] as $rule ) {
			try {
				$verdict = $this->evaluator->evaluate( $rule, $facts );
			}
			catch ( \Throwable $e ) {
				continue;
			}
			if ( $verdict !== RuleEvaluator::NO_MATCH ) {
				$this->log( $rule, $point->value, $verdict, $facts );
				return ( $this->respond )( $rule, $point->value );
			}
		}
		return null;
	}

	/**
	 * Plugin and theme activity come from the ruleset, as the early pass takes them.
	 */
	private function requestFacts() :array {
		return \array_merge(
			WordPressFacts::request(),
			RuleEvaluator::rulesetFacts( $this->ruleset ),
			[ 'is_admin' => WordPressFacts::isAdmin() ]
		);
	}

	private function log( array $rule, string $point, string $verdict, array $facts ) :void {
		$path = $this->ruleset[ 'events' ] ?? null;
		if ( !\is_string( $path ) || $path === '' ) {
			return;
		}
		BlockLog::append( $path, BlockLog::entry( $this->ruleset, $rule, $facts, WordPressFacts::server(), $point, $verdict ) );
	}

	/**
	 * @param string $point an EvaluationPoint value, or 'early' for the early pass
	 * @return \WP_Error|never
	 */
	public function sendForbidden( array $rule, string $point ) {
		if ( $point === EvaluationPoint::Rest->value && \class_exists( '\WP_Error' ) ) {
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
