<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * Requests the installer makes to this site to check that it still loads and whether the stub runs.
 */
interface LoopbackProbe {

	/**
	 * Shield's verification response through admin-ajax.php.
	 */
	public function verify() :ProbeResult;

	/**
	 * Shield's verification response through the home page, the way visitors reach the site. As with
	 * WordPress's plugin editor check, the response content is checked, not only the status, because a host
	 * that displays errors can return a fatal error with status 200.
	 */
	public function home() :ProbeResult;

	/**
	 * Before checks that guard a change to the site.
	 */
	public function prepare() :void;

	/**
	 * A stub file requested directly, to check the web server can run it.
	 */
	public function smoke( string $url ) :ProbeResult;
}
