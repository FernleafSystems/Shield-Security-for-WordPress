<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\{
	BlockLog,
	IpBypass,
	RuleEvaluator
};

/**
 * Checks the early-capable virtual patching rules before WordPress loads. The rules that need WordPress are
 * left to RequestGuard.
 *
 * Runs from the early loader stub without Composer or WordPress, so it only uses the self-contained virtual
 * patching classes.
 */
final class EarlyPass {

	private array $ruleset;

	private array $server;

	private array $query;

	private array $form;

	private RuleEvaluator $evaluator;

	private bool $complete = false;

	public function __construct( array $ruleset, array $server, array $query, array $form ) {
		$this->ruleset = $ruleset;
		$this->server = $server;
		$this->query = $query;
		$this->form = $form;
		$this->evaluator = new RuleEvaluator();
	}

	/**
	 * Whether the requested PHP file belongs to this WordPress site: inside one of its directories, and not
	 * inside another WordPress installed in a folder below it, which is common on shared hosting. Other
	 * applications and other sites are never checked against this site's rules.
	 * @param string[] $scopeDirs
	 */
	public static function coversScript( string $scriptFile, array $scopeDirs ) :bool {
		$script = $scriptFile !== '' && \function_exists( 'realpath' ) ? \realpath( $scriptFile ) : false;
		if ( !\is_string( $script ) ) {
			return false;
		}
		$script = \str_replace( '\\', '/', $script );

		foreach ( $scopeDirs as $root ) {
			$root = \rtrim( \str_replace( '\\', '/', (string)$root ), '/' );
			if ( $root === '' || \strpos( $script, $root.'/' ) !== 0 ) {
				continue;
			}
			for ( $dir = \dirname( $script ); \strlen( $dir ) > \strlen( $root ); $dir = \dirname( $dir ) ) {
				if ( \is_file( $dir.'/wp-load.php' ) ) {
					return false;
				}
			}
			return true;
		}
		return false;
	}

	/**
	 * @return array|null the rule that blocks this request
	 */
	public function run() :?array {
		$this->complete = false;
		if ( IpBypass::isBypassed( $this->ruleset, $this->server ) ) {
			$this->complete = true;
			return null;
		}

		$facts = RuleEvaluator::requestFacts( $this->server, $this->query, $this->form );
		$facts[ 'active_plugins' ] = \is_array( $this->ruleset[ 'active_plugins' ] ?? null ) ? $this->ruleset[ 'active_plugins' ] : [];
		$facts[ 'active_themes' ] = \is_array( $this->ruleset[ 'active_themes' ] ?? null ) ? $this->ruleset[ 'active_themes' ] : [];

		$evaluatedAll = true;
		foreach ( $this->ruleset[ 'rules' ] as $rule ) {
			try {
				if ( !\is_array( $rule ) || !$this->evaluator->isEarlyCapable( $rule ) ) {
					continue;
				}
				$verdict = $this->evaluator->evaluate( $rule, $facts );
			}
			catch ( \Throwable $e ) {
				// Left for WordPress to check, which it only does when the early pass isn't complete.
				$evaluatedAll = false;
				continue;
			}

			if ( $verdict !== RuleEvaluator::NO_MATCH ) {
				$this->log( $rule, $verdict, $facts );
				return $rule;
			}
		}

		$this->complete = $evaluatedAll;
		return null;
	}

	/**
	 * Whether every early-capable rule was decided, so WordPress may skip them.
	 */
	public function isComplete() :bool {
		return $this->complete;
	}

	private function log( array $rule, string $verdict, array $facts ) :void {
		$path = $this->ruleset[ 'events' ] ?? null;
		if ( !\is_string( $path ) || $path === '' ) {
			return;
		}
		$remote = $this->server[ 'REMOTE_ADDR' ] ?? '';
		BlockLog::append( $path, [
			'ts'         => \time(),
			'rule'       => (string)( $rule[ 'id' ] ?? '' ),
			'advisories' => \is_array( $rule[ 'advisories' ] ?? null ) ? $rule[ 'advisories' ] : [],
			'ip'         => IpBypass::requestIp( $this->ruleset[ 'bypass' ][ 'ip_source' ] ?? null, $this->server )
							?? ( \is_string( $remote ) ? $remote : '' ),
			'method'     => $facts[ 'method' ],
			'path'       => $facts[ 'path' ],
			'pass'       => 'early',
			'verdict'    => $verdict,
		] );
	}
}
