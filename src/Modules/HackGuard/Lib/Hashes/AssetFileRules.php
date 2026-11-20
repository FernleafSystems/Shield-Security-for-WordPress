<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes;

/**
 * Asset files into which the vendor writes fresh values, in known places, on every download. The WP Hashes API
 * publishes such a file's hash with those values emptied, so a file whose own bytes don't match is compared again with
 * the values emptied the same way, and crowd-sourced hashes are submitted that way. Every other byte is still verified.
 * Each rule must stay byte-identical to the API's.
 */
class AssetFileRules {

	/**
	 * Keyed by path relative to the asset root: [ pattern, replacement ].
	 * edd-build-manifest-v1: Easy Digital Downloads writes an apparently encrypted manifestChecksum into
	 * vendor/build.json on every download, even for the same licence and build.
	 * buddyboss-platform-edition-v1: BuddyBoss Platform's edition downloads name their edition in bp-loader.php's
	 * PLATFORM_EDITION and are otherwise identical.
	 * wp-rocket-licence-data-v1: WP Rocket's download writes the customer's licence key and account email into
	 * licence-data.php, which must define each exactly once, key first, as a single string, and name neither anywhere
	 * else but in defined() checks.
	 */
	private const RULES = [
		'vendor/build.json' => [
			'#"manifestChecksum":"[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{2}(?:[A-Za-z0-9+]|\\\\/)+={0,2}"#',
			'"manifestChecksum":""',
		],
		'bp-loader.php'     => [
			"#define\\('PLATFORM_EDITION', '[a-z0-9-]+'\\);#",
			"define('PLATFORM_EDITION', '');",
		],
		'licence-data.php'  => [
			"#\\A((?:(?!WP_ROCKET_(?:KEY|EMAIL)(?!'\\s*\\))).)*define\\( 'WP_ROCKET_KEY', )'(?:[^'\\\\]|\\\\.)*'(?=\\s*\\);)((?:(?!WP_ROCKET_(?:KEY|EMAIL)(?!'\\s*\\))).)*define\\( 'WP_ROCKET_EMAIL', )'(?:[^'\\\\]|\\\\.)*'(?=\\s*\\);)((?:(?!WP_ROCKET_(?:KEY|EMAIL)(?!'\\s*\\))).)*)\\z#s",
			"\$1''\$2''\$3",
		],
	];

	/**
	 * The content to compare instead, or null when no rule covers the path or the content lacks exactly one match.
	 */
	public function apply( string $relativePath, string $content ) :?string {
		$rule = self::RULES[ $relativePath ] ?? null;
		if ( $rule === null ) {
			return null;
		}
		$applied = \preg_replace( $rule[ 0 ], $rule[ 1 ], $content, -1, $count );
		return \is_string( $applied ) && $count === 1 ? $applied : null;
	}
}
