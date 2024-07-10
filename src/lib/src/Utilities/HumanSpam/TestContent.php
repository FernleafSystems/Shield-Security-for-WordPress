<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\HumanSpam;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class TestContent {

	use PluginControllerConsumer;

	/**
	 * @var string[]
	 */
	private $list;

	/**
	 * @return string[][]
	 */
	public function findSpam( array $itemsToTest, bool $finishAfterFirst = true ) :array {
		$spamFound = [];

		foreach ( $this->getSpamList() as $word ) {
			foreach ( \array_map( '\strval', \array_filter( $itemsToTest ) ) as $key => $item ) {
				if ( \stripos( $item, $word ) !== false ) {

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
		if ( !\is_array( $this->list ) ) {
			$FS = Services::WpFs();
			$file = $this->getFile();
			if ( $FS->exists( $file )
				 && Services::Request()->ts() - $FS->getModifiedTime( $file ) < \MONTH_IN_SECONDS ) {
				$this->list = \array_map( '\base64_decode', \explode( "\n", (string)$FS->getFileContent( $file, true ) ) );
			}
			else {
				$this->list = $this->downloadBlacklist();
				$this->storeList( $this->list );
			}
		}
		return $this->list;
	}

	private function downloadBlacklist() :array {
		$rawList = Services::HttpRequest()
						   ->getContent( self::con()->cfg->configuration->def( 'url_spam_blacklist_terms' ) );
		return \array_filter( \array_map( '\trim', \explode( "\n", $rawList ) ) );
	}

	private function storeList( array $list ) {
		if ( !empty( $list ) && !empty( $this->getFile() ) ) {
			Services::WpFs()->putFileContent(
				$this->getFile(),
				\implode( "\n", \array_map( 'base64_encode', $list ) ),
				true
			);
		}
	}

	private function getFile() :string {
		return self::con()->cache_dir_handler->cacheItemPath( 'spamblacklist.txt' );
	}
}