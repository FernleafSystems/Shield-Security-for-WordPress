<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\HumanSpam;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

class TestContent {

	use Modules\PluginControllerConsumer;

	/**
	 * @var string[]
	 */
	private $list;

	/**
	 * @param array $itemsToTest
	 * @param bool  $finishAfterFirst
	 * @return string[][]
	 */
	public function findSpam( array $itemsToTest, bool $finishAfterFirst = true ) :array {
		$spamFound = [];

		foreach ( $this->getSpamList() as $word ) {
			foreach ( $itemsToTest as $key => $item ) {
				if ( stripos( $item, $word ) !== false ) {

					if ( !isset( $spamFound[ $word ] ) ) {
						$spamFound[ $word ] = [];
					}
					$spamFound[ $word ][ $key ] = $item;

					if ( $finishAfterFirst ) {
						break 2;
					}
				}
			}
		}

		return $spamFound;
	}

	private function getSpamList() :array {
		if ( empty( $this->list ) ) {
			$FS = Services::WpFs();
			$file = $this->getFile();
			if ( !$FS->exists( $file ) || Services::Request()
												  ->ts() - $FS->getModifiedTime( $file ) > WEEK_IN_SECONDS ) {
				$this->importBlacklist();
			}
			$this->list = array_map( 'base64_decode', explode( "\n", $FS->getFileContent( $file, true ) ) );
		}
		return $this->list;
	}

	private function importBlacklist() :bool {
		$success = false;
		$mod = $this->getCon()->getModule_Comments();
		$rawList = Services::HttpRequest()->getContent( $mod->getOptions()->getDef( 'url_spam_blacklist_terms' ) );
		if ( !empty( $rawList ) ) {
			$success = Services::WpFs()->putFileContent(
				$this->getFile(),
				implode( "\n", array_map( 'base64_encode', array_filter( array_map( 'trim', explode( "\n", $rawList ) ) ) ) ),
				true
			);
		}
		return $success;
	}

	private function getFile() :string {
		return $this->getCon()->getModule_Comments()->getSpamBlacklistFile();
	}
}