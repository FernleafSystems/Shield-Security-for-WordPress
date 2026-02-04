<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;

/**
 * @property string       $class
 * @property string       $slug
 * @property string       $name
 * @property string       $description
 * @property bool         $is_valid
 * @property string       $wp_hook
 * @property int          $wp_hook_level
 * @property int          $wp_hook_priority
 * @property bool         $result
 * @property bool         $immediate_exec_response
 * @property string[]     $flags
 * @property string[]     $prerequisites
 * @property ConditionsVO $conditions
 * @property array[]      $responses
 * @property string[]     $all_actions
 */
class RuleVO extends DynPropertiesClass {

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'wp_hook':
				if ( empty( $value ) ) {
					$value = $this->determineWpHook();
					$this->wp_hook = $value;
				}
				break;

			case 'wp_hook_priority':
				$value = \is_numeric( $value ) ? (int)$value : $this->determineWpHookPriority();
				break;

			case 'immediate_exec_response':
				$value = (bool)$value;
				break;

			case 'flags':
			case 'prerequisites':
			case 'responses':
				if ( !\is_array( $value ) ) {
					$value = [];
				}
				break;

			case 'conditions':
				$value = ( new ConditionsVO() )->applyFromArray( \is_array( $value ) ? $value : [] );
				break;

			case 'is_valid':
				$value = $value || $value === null;
				break;

			default:
				break;
		}
		return $value;
	}

	private function determineWpHook() :string {
		$hook = '';
		if ( isset( $this->prerequisites[ 'is_logged_in' ] ) ) {
			$hook = 'init';
		}
		return $hook;
	}

	private function determineWpHookPriority() :int {
		switch ( $this->wp_hook ) {
			case 'init':
				$priority = HookTimings::INIT_DEFAULT_RULES_HOOK;
				break;
			default:
				$priority = 0;
		}
		return $priority;
	}
}