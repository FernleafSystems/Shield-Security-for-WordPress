<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AdminNotices {

	use Shield\Modules\ModConsumer;

	public function run() {
		add_filter( $this->getMod()->prefix( 'collectNotices' ), [ $this, 'addNotices' ] );
	}

	public function addNotices( $aNotices ) {
		$oMod = $this->getMod();
		$oOpts = $oMod->getOptions();

		foreach ( $oOpts->getAdminNotices() as $aNotDef ) {
			$aNotDef = Services::DataManipulation()
							   ->mergeArraysRecursive(
								   [
									   'schedule'          => 'conditions',
									   'type'              => 'promo',
									   'plugin_page_only'  => true,
									   'valid_admin'       => true,
									   'plugin_admin_only' => true,
									   'twig'              => false,
									   'display'           => true,
								   ],
								   $aNotDef
							   );
			$oNotice = ( new Shield\Utilities\AdminNotices\NoticeVO() )->applyFromArray( $aNotDef );

			$this->preProcessNotice( $oNotice );
			if ( $oNotice->display ) {
				try {
					$this->processNotice( $oNotice );
					if ( $oNotice->display ) {
						$aNotices[] = $oNotice;
					}
				}
				catch ( \Exception $oE ) {
				}
			}
		}

		return $aNotices;
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 */
	protected function preProcessNotice( $oNotice ) {
		$oCon = $this->getCon();
		if ( $oNotice->valid_admin && !$oCon->isValidAdminArea() ) {
			$oNotice->display = false;
		}
		if ( $oNotice->plugin_admin_only && !$oCon->isPluginAdmin() ) {
			$oNotice->display = false;
		}
		$oNotice->template = '/notices/'.$oNotice->id;
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 * @throws \Exception
	 */
	protected function processNotice( $oNotice ) {
		throw new \Exception( 'Unsupported Notice ID: '.$oNotice->id );
	}
}