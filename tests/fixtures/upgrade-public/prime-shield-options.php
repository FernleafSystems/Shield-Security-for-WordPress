<?php
// WP-CLI eval-file wraps helpers before execution, so this file cannot declare strict_types first.

$report = [
	'profile'       => 'not-applied',
	'applied'       => [],
	'skipped'       => [],
	'excluded'      => [],
	'safety_resets' => [],
	'errors'        => [],
];

$plugin = function_exists( 'shield_security_get_plugin' ) ? shield_security_get_plugin() : null;
$con = is_object( $plugin ) && method_exists( $plugin, 'getController' ) ? $plugin->getController() : $plugin;
if ( !is_object( $con ) ) {
	$report[ 'errors' ][] = 'Shield controller unavailable.';
	echo wp_json_encode( $report );
	return;
}

$opts = $con->opts ?? null;
$configuration = $con->cfg->configuration ?? null;
if ( !is_object( $opts ) || !is_object( $configuration ) ) {
	$report[ 'errors' ][] = 'Options runtime unavailable.';
	echo wp_json_encode( $report );
	return;
}

try {
	if ( isset( $con->comps->security_profiles ) && method_exists( $con->comps->security_profiles, 'applyLevel' ) ) {
		$con->comps->security_profiles->applyLevel( 'strong' );
		$report[ 'profile' ] = 'strong';
	}
}
catch ( Throwable $e ) {
	$report[ 'profile' ] = 'failed';
	$report[ 'errors' ][] = 'Profile apply failed: '.$e->getMessage();
}

$options = method_exists( $configuration, 'transferableOptions' )
	? $configuration->transferableOptions()
	: (array)( $configuration->options ?? [] );

$excludedPatterns = [
	'/license|key|secret|token|api|url|email|notify|webhook/i',
	'/importexport|import_export|master|slave/i',
	'/admin_access|rename_wplogin|hide_login|sec_admin|security_admin/i',
	'/delete|uninstall|reset|lockdown/i',
	'/crowdsec|hibp|pwned/i',
];
$safetyResetPatterns = [
	'/admin_access|rename_wplogin|hide_login|sec_admin|security_admin/i',
];

foreach ( $options as $key => $definition ) {
	$optionKey = is_string( $key ) ? $key : (string)( is_array( $definition ) ? ( $definition[ 'key' ] ?? $definition[ 'name' ] ?? '' ) : '' );
	if ( $optionKey === '' || !is_array( $definition ) ) {
		continue;
	}

	foreach ( $excludedPatterns as $pattern ) {
		if ( preg_match( $pattern, $optionKey ) !== 1 ) {
			continue;
		}

		$report[ 'excluded' ][] = $optionKey;
		foreach ( $safetyResetPatterns as $resetPattern ) {
			if ( preg_match( $resetPattern, $optionKey ) === 1 ) {
				shield_upgrade_test_safety_reset_option( $opts, $optionKey, $definition, $report );
				break;
			}
		}
		continue 2;
	}

	try {
		if ( method_exists( $opts, 'optHasAccess' ) && !$opts->optHasAccess( $optionKey ) ) {
			$report[ 'skipped' ][] = $optionKey;
			continue;
		}

		$value = shield_upgrade_test_value_for_option( $definition );
		if ( $value === null ) {
			$report[ 'skipped' ][] = $optionKey;
			continue;
		}

		if ( method_exists( $opts, 'optSet' ) ) {
			$opts->optSet( $optionKey, $value );
			$report[ 'applied' ][] = $optionKey;
		}
	}
	catch ( Throwable $e ) {
		$report[ 'skipped' ][] = $optionKey;
		$report[ 'errors' ][] = $optionKey.': '.$e->getMessage();
	}
}

try {
	if ( method_exists( $opts, 'store' ) ) {
		$opts->store();
	}
}
catch ( Throwable $e ) {
	$report[ 'errors' ][] = 'Options store failed: '.$e->getMessage();
}

echo wp_json_encode( $report );

/**
 * @param array<string,mixed> $definition
 * @param array<string,mixed> $report
 */
function shield_upgrade_test_safety_reset_option( $opts, string $optionKey, array $definition, array &$report ) :void {
	if ( !method_exists( $opts, 'optSet' ) ) {
		return;
	}

	try {
		$opts->optSet( $optionKey, shield_upgrade_test_safe_disabled_value( $definition ) );
		$report[ 'safety_resets' ][] = $optionKey;
	}
	catch ( Throwable $e ) {
		$report[ 'errors' ][] = $optionKey.' safety reset failed: '.$e->getMessage();
	}
}

/**
 * @param array<string,mixed> $definition
 * @return mixed
 */
function shield_upgrade_test_safe_disabled_value( array $definition ) {
	$type = (string)( $definition[ 'type' ] ?? '' );
	if ( $type === 'boolean' ) {
		return false;
	}
	if ( $type === 'array' || $type === 'multiple_select' ) {
		return [];
	}
	return 'N';
}

/**
 * @param array<string,mixed> $definition
 * @return mixed
 */
function shield_upgrade_test_value_for_option( array $definition ) {
	$type = (string)( $definition[ 'type' ] ?? '' );

	switch ( $type ) {
		case 'checkbox':
			return 'Y';
		case 'boolean':
			return true;
		case 'integer':
			return max( 1, (int)( $definition[ 'default' ] ?? 1 ) );
		case 'select':
			$values = shield_upgrade_test_option_values( $definition );
			return $values[ 1 ] ?? $values[ 0 ] ?? null;
		case 'multiple_select':
			return array_slice( shield_upgrade_test_option_values( $definition ), 0, 3 );
		case 'array':
			return is_array( $definition[ 'default' ] ?? null ) ? $definition[ 'default' ] : [];
		default:
			return null;
	}
}

/**
 * @param array<string,mixed> $definition
 * @return string[]
 */
function shield_upgrade_test_option_values( array $definition ) :array {
	$candidates = $definition[ 'value_options' ] ?? $definition[ 'values' ] ?? $definition[ 'options' ] ?? [];
	if ( !is_array( $candidates ) ) {
		return [];
	}

	$values = [];
	foreach ( $candidates as $key => $value ) {
		$candidate = is_string( $key ) ? $key : ( is_string( $value ) ? $value : null );
		if ( $candidate !== null && $candidate !== '' ) {
			$values[] = $candidate;
		}
	}

	return array_values( array_unique( $values ) );
}
