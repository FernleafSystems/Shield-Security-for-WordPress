<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * Requests the installer makes to this site to check that it still loads and whether the stub runs.
 */
interface LoopbackProbe {

	/**
	 * Shield's verification response, which reports whether the stub ran for that request.
	 */
	public function verify() :ProbeResult;

	/**
	 * The site's home page, to check it still loads.
	 */
	public function home() :ProbeResult;

	/**
	 * The stub requested directly, to check the web server can run it.
	 */
	public function smoke() :ProbeResult;
}
