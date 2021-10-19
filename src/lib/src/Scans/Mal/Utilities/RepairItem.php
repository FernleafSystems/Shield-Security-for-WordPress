<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\ResultItem;
use FernleafSystems\Wordpress\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

class RepairItem extends Shield\Scans\Base\Utilities\RepairItemBase {

	use Shield\Modules\ModConsumer;
	use Shield\Scans\Common\ScanItemConsumer;

	public function repairItem() :bool {
		/** @var ResultItem $item */
		$item = $this->getScanItem();
		/** @var Shield\Modules\HackGuard\Options $opts */
		$opts = $this->getOptions();
		$success = false;

		try {
			$canRepair = $this->canRepair();
		}
		catch ( \Exception $e ) {
			$canRepair = false;
		}

		if ( $canRepair ) {

			if ( Services\Services::CoreFileHashes()->isCoreFile( $item->path_fragment ) ) {
				$success = $this->repairCoreItem();
			}
			else {
				$plugin = ( new WpOrg\Plugin\Files() )->findPluginFromFile( $item->path_full );
				if ( $plugin instanceof Services\Core\VOs\Assets\WpPluginVo && $plugin->isWpOrg() ) {

					$success = $this->repairItemInPlugin();
				}
				else {
					$theme = ( new WpOrg\Theme\Files() )->findThemeFromFile( $item->path_full );
					if ( $theme instanceof Services\Core\VOs\Assets\WpThemeVo && $theme->isWpOrg() ) {

						$success = $this->repairItemInTheme();
					}
					elseif ( $opts->isMalAutoRepairSurgical() ) {
						$success = $this->repairSurgicalItem();
					}
				}
			}
		}

		if ( $success ) {
			( new Shield\Scans\Mal\Utilities\FalsePositiveReporter() )
				->setMod( $this->getMod() )
				->reportResultItem( $item, false );
		}

		return $success;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function canRepair() :bool {
		return $this->canAutoRepairFromSource( $this->getScanItem() );
	}

	/**
	 * Can only repair a WP Core file, or a plugin that is WP.org, has no update available
	 * and the latest version uses SVN tags.
	 * TODO: Also need to detect whether the file is unrecognised or not. If unrecog we can't repair.
	 * @param ResultItem $item
	 * @return bool
	 * @throws \Exception
	 */
	public function canAutoRepairFromSource( ResultItem $item ) :bool {

		$canRepair = Services\Services::CoreFileHashes()->isCoreFile( $item->path_fragment );
		if ( !$canRepair ) {

			$plugin = ( new WpOrg\Plugin\Files() )->findPluginFromFile( $item->path_full );
			if ( !empty( $plugin ) ) {
				if ( !$plugin->isWpOrg() ) {
					throw new \Exception( sprintf(
							__( "%s not installed from WordPress.org.", 'wp-simple-firewall' ),
							__( 'Plugin', 'wp-simple-firewall' )
						)
					);
				}
				if ( !$plugin->svn_uses_tags ) {
					throw new \Exception( __( "Plugin developer doesn't use SVN tags for official releases.", 'wp-simple-firewall' ) );
				}

				try {
					$canRepair = ( new Shield\Modules\HackGuard\Lib\Hashes\Query() )
						->setMod( $this->getMod() )
						->fileExistsInHash( $item->path_fragment );
				}
				catch ( \Exception $e ) {
					error_log( var_export( $e->getMessage(), true ) );
					$canRepair = false;
				}
			}
			else {
				$theme = ( new WpOrg\Theme\Files() )->findThemeFromFile( $item->path_full );
				if ( !empty( $theme ) ) {
					if ( $theme->is_child ) {
						throw new \Exception( sprintf(
								__( "%s is a child of another theme.", 'wp-simple-firewall' ),
								__( 'Theme', 'wp-simple-firewall' )
							)
						);
					}
					if ( !$theme->isWpOrg() ) {
						throw new \Exception( sprintf(
								__( "%s not installed from WordPress.org.", 'wp-simple-firewall' ),
								__( 'Theme', 'wp-simple-firewall' )
							)
						);
					}
					if ( !( new WpOrg\Theme\Versions() )
						->setWorkingSlug( $theme->stylesheet )
						->exists( $theme->version, true ) ) {
						throw new \Exception( __( "Theme version doesn't appear to exist.", 'wp-simple-firewall' ) );
					}

					try {
						$canRepair = ( new Shield\Modules\HackGuard\Lib\Hashes\Query() )
							->setMod( $this->getMod() )
							->fileExistsInHash( $item->path_fragment );
					}
					catch ( \Exception $e ) {
						error_log( var_export( $e->getMessage(), true ) );
						$canRepair = false;
					}
				}
			}
		}

		return $canRepair;
	}

	private function repairCoreItem() :bool {
		/** @var ResultItem $item */
		$item = $this->getScanItem();

		$files = Services\Services::WpGeneral()->isClassicPress() ? new WpOrg\Cp\Files() : new WpOrg\Wp\Files();
		try {
			$success = $files->replaceFileFromVcs( $item->path_fragment );
		}
		catch ( \InvalidArgumentException $e ) {
			$success = false;
		}
		return $success;
	}

	private function repairSurgicalItem() :bool {
		/** @var ResultItem $item */
		$item = $this->getScanItem();

		$success = false;
		foreach ( array_keys( $item->file_lines ) as $lineNumber ) {
			try {
				( new Services\Utilities\File\RemoveLineFromFile() )->run( $item->path_full, $lineNumber );
				$success = true;
			}
			catch ( \Exception $e ) {
				$success = false;
				break;
			}
		}
		return $success;
	}

	private function repairItemInPlugin() :bool {
		/** @var ResultItem $item */
		$item = $this->getScanItem();

		$success = false;

		$files = new WpOrg\Plugin\Files();
		try {
			if ( $files->isValidFileFromPlugin( $item->path_full ) ) {
				$success = $files->replaceFileFromVcs( $item->path_full );
			}
		}
		catch ( \InvalidArgumentException $e ) {
		}

		return $success;
	}

	private function repairItemInTheme() :bool {
		/** @var ResultItem $item */
		$item = $this->getScanItem();

		$success = false;

		$files = new WpOrg\Theme\Files();
		try {
			if ( $files->isValidFileFromTheme( $item->path_full ) ) {
				$success = $files->replaceFileFromVcs( $item->path_full );
			}
		}
		catch ( \InvalidArgumentException $e ) {
		}

		return $success;
	}
}