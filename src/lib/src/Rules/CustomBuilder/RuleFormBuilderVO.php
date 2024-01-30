<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property string  $name
 * @property string  $description
 * @property int     $edit_rule_id
 * @property array   $conditions
 * @property array   $responses
 * @property array[] $checks
 * @property int     $count_set_conditions
 * @property int     $count_set_responses
 * @property bool    $has_unset_condition
 * @property bool    $has_unset_response
 * @property string  $conditions_logic
 * @property bool    $has_errors
 * @property array[] $warnings
 * @property bool    $ready_to_create
 * @property string  $form_builder_version
 * @property int     $created_at
 */
class RuleFormBuilderVO extends DynPropertiesClass {

}