<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Wordpress\Services\Services;

class TrackDirectFileAccess extends Base {

	const OPT_KEY = 'track_invalidscript';

	private $script = null;

	protected function process() {
		$this->testScript();
	}

	private function testScript() {
		$req = Services::Request();

		// For the moment we only handle the actual file name itself.
		$scripts = array_unique( array_map( 'basename', array_filter( [
			$req->server( 'SCRIPT_NAME' ),
			$req->server( 'SCRIPT_FILENAME' ),
			$req->server( 'PHP_SELF' )
		] ) ) );
		// There should only ever be 1.  More than 1 means a strange configuration which we wont touch.
		if ( count( $scripts ) === 1 ) {
			$script = array_shift( $scripts );
			if ( preg_match( '#[/\\\\]#', $script ) === 0 && !in_array( $script, $this->getAllowedScripts() ) ) {
				$this->script = $script;
				$this->doTransgression();
			}
		}
	}

	protected function getAllowedScripts() :array {
		return [
			'index.php',
			'admin-ajax.php',
			'wp-activate.php',
			'wp-links-opml.php',
			'wp-cron.php',
			'wp-login.php',
			'wp-mail.php',
			'wp-comments-post.php',
			'wp-signup.php',
			'wp-trackback.php',
			'xmlrpc.php',
		];
	}

	protected function getAuditData() :array {
		return [
			'script' => $this->script
		];
	}
}
