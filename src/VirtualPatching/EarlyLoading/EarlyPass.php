<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

use FernleafSystems\Orle\VirtualPatchingRules\RuleEvaluator;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\{
	BlockLog,
	IpBypass
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

	private array $files;

	private RuleEvaluator $evaluator;

	private bool $complete = false;

	/**
	 * @param array $files the uploaded files as PHP built $_FILES
	 */
	public function __construct( array $ruleset, array $server, array $query, array $form, array $files ) {
		$this->ruleset = $ruleset;
		$this->server = $server;
		$this->query = $query;
		$this->form = $form;
		$this->files = $files;
		$this->evaluator = new RuleEvaluator();
	}

	/**
	 * Whether the requested PHP file belongs to this WordPress site, so other applications and other sites
	 * on the same hosting account are never checked against this site's rules. The scope lists:
	 * - 'files': exact files, such as the site's front controller when WordPress has its own directory;
	 * - 'root_dirs': directories whose own PHP files are covered, but not their subdirectories (ABSPATH);
	 * - 'dirs': directories covered with everything below them (wp-admin, wp-includes, the content
	 *   directory), except inside another WordPress installed in a folder there.
	 */
	public static function coversScript( string $scriptFile, array $scope ) :bool {
		$script = $scriptFile !== '' && \function_exists( 'realpath' ) ? \realpath( $scriptFile ) : false;
		if ( !\is_string( $script ) ) {
			return false;
		}
		$script = self::normalise( $script );

		foreach ( self::scopeList( $scope, 'files' ) as $file ) {
			if ( $script === $file ) {
				return true;
			}
		}
		foreach ( self::scopeList( $scope, 'root_dirs' ) as $dir ) {
			if ( \dirname( $script ) === $dir ) {
				return true;
			}
		}
		foreach ( self::scopeList( $scope, 'dirs' ) as $root ) {
			if ( \strpos( $script, $root.'/' ) === 0 ) {
				for ( $dir = \dirname( $script ); \strlen( $dir ) > \strlen( $root ); $dir = \dirname( $dir ) ) {
					if ( \is_file( $dir.'/wp-load.php' ) ) {
						return false;
					}
				}
				return true;
			}
		}
		return false;
	}

	/**
	 * @return string[]
	 */
	private static function scopeList( array $scope, string $key ) :array {
		$list = [];
		foreach ( \is_array( $scope[ $key ] ?? null ) ? $scope[ $key ] : [] as $path ) {
			if ( \is_string( $path ) && \trim( $path, '/\\' ) !== '' ) {
				$list[] = self::normalise( $path );
			}
		}
		return $list;
	}

	private static function normalise( string $path ) :string {
		return \rtrim( \str_replace( '\\', '/', $path ), '/' );
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

		$facts = \array_merge(
			RuleEvaluator::requestFacts( $this->server, $this->query, $this->form ),
			RuleEvaluator::uploadFacts( $this->files ),
			RuleEvaluator::rulesetFacts( $this->ruleset )
		);

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

			if ( $verdict === RuleEvaluator::NO_MATCH ) {
				continue;
			}
			// A log rule's match is recorded and checking carries on, so it can't hide a block rule after it.
			if ( BlockLog::isLogRule( $rule ) ) {
				if ( BlockLog::firstObservation( (string)( $rule[ 'id' ] ?? '' ) ) ) {
					$this->log( $rule, $verdict, $facts );
				}
				continue;
			}
			$this->log( $rule, $verdict, $facts );
			return $rule;
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
		if ( \is_string( $path ) && $path !== '' ) {
			BlockLog::append( $path, BlockLog::entry( $this->ruleset, $rule, $facts, $this->server, 'early', $verdict ) );
		}
	}
}
