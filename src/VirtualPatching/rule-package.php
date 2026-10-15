<?php declare( strict_types=1 );

/**
 * The rule package classes Shield's request passes require by path, because they run before Composer loads: the
 * early pass and the checks as Shield's plugin file loads. The package is the orchestrator's rule language,
 * installed with Composer; its classes in this list are self-contained.
 *
 * @return array<string, string> file by fully qualified class name
 */
$source = \dirname( __DIR__, 2 ).'/vendor/fernleafsystems/orle-virtual-patching-rules/src/';
$files = [];
foreach ( [ 'RuleSchema', 'EvaluationPoint', 'RuleEvaluator', 'WordPressFacts' ] as $class ) {
	$files[ 'FernleafSystems\\Orle\\VirtualPatchingRules\\'.$class ] = $source.$class.'.php';
}
return $files;
