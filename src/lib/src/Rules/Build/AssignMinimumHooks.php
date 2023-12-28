<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	RuleVO,
	Utility\ExtractSubConditions,
	WPHooksOrder
};

/**
 * Assigns the most appropriate WP Hook to a rule based on its (sub)conditions.
 */
class AssignMinimumHooks {

	/**
	 * @var RuleVO[]
	 */
	private $rules;

	public function __construct( array $rules ) {
		$this->rules = $rules;
	}

	public function run() :void {
		foreach ( $this->rules as $rule ) {
			$minimumHook = WPHooksOrder::NONE;
			/** @var Conditions\Base[] $allConditions */
			try {
				foreach ( ( new ExtractSubConditions() )->fromRule( $rule )[ 'classes' ] as $condition ) {
					$minimumHook = \max( $minimumHook, $condition::MinimumHook() );
				}
				$rule->wp_hook_level = \max( $minimumHook, $rule->wp_hook_level );
				$rule->wp_hook = WPHooksOrder::HOOK_NAME( $rule->wp_hook_level );
				$rule->is_valid = true;
			}
			catch ( \Exception $e ) {
				$rule->is_valid = false;
				error_log( sprintf( '[Rule::%s] %s', $rule->slug, $e->getMessage() ) );
			}
		}
	}
}