<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property string $rule_name
 * @property string $rule_description
 * @property array  $conditions
 * @property array  $responses
 * @property int    $count_set_conditions
 * @property int    $count_set_responses
 * @property bool   $has_unset_condition
 * @property bool   $has_unset_response
 * @property string $conditions_logic
 * @property bool   $has_errors
 * @property bool   $ready_to_create
 */
class RuleFormBuilderVO extends DynPropertiesClass {

}