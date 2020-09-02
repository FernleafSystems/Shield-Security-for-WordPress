<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class UI {

	use ModConsumer;

	/**
	 * Will initiate the plugin options structure for use by the UI builder.
	 * It doesn't set any values, just populates the array created in buildOptions()
	 * with values stored.
	 * It has to handle the conversion of stored values to data to be displayed to the user.
	 */
	public function buildOptions() {

		$bPremiumEnabled = $this->getCon()->isPremiumExtensionsEnabled();

		$opts = $this->getOptions();
		$aOptions = $opts->getOptionsForPluginUse();

		foreach ( $aOptions as $nSectionKey => $aSection ) {

			if ( !empty( $aSection[ 'options' ] ) ) {

				foreach ( $aSection[ 'options' ] as $nKey => $aOption ) {
					$aOption[ 'is_value_default' ] = ( $aOption[ 'value' ] === $aOption[ 'default' ] );
					$bIsPrem = isset( $aOption[ 'premium' ] ) && $aOption[ 'premium' ];
					if ( !$bIsPrem || $bPremiumEnabled ) {
						$aSection[ 'options' ][ $nKey ] = $this->buildOptionForUi( $aOption );
					}
					else {
						unset( $aSection[ 'options' ][ $nKey ] );
					}
				}

				if ( empty( $aSection[ 'options' ] ) ) {
					unset( $aOptions[ $nSectionKey ] );
				}
				else {
					try {
						$aStrings = $this->getMod()
										 ->getStrings()
										 ->getSectionStrings( $aSection[ 'slug' ] );
						foreach ( $aStrings as $sKey => $sVal ) {
							unset( $aSection[ $sKey ] );
							$aSection[ $sKey ] = $sVal;
						}
					}
					catch ( \Exception $oE ) {
					}
					$aOptions[ $nSectionKey ] = $aSection;
				}

				$aWarnings = [];
				if ( !$opts->isSectionReqsMet( $aSection[ 'slug' ] ) ) {
					$aWarnings[] = __( 'Unfortunately your WordPress and/or PHP versions are too old to support this feature.', 'wp-simple-firewall' );
				}
				$aOptions[ $nSectionKey ][ 'warnings' ] = array_merge(
					$aWarnings,
					$this->getSectionWarnings( $aSection[ 'slug' ] )
				);
				$aOptions[ $nSectionKey ][ 'notices' ] = $this->getSectionNotices( $aSection[ 'slug' ] );

				if ( !empty( $aSection[ 'help_video_id' ] ) ) {
					$sHelpVideoUrl = $this->getHelpVideoUrl( $aSection[ 'help_video_id' ] );
				}
				else {
					$sHelpVideoUrl = '';
				}
				$aOptions[ $nSectionKey ][ 'help_video_url' ] = ''; /* Remove on Shield 9.0 */
			}
		}

		return $aOptions;
	}

	/**
	 * @param array $aOptParams
	 * @return array
	 */
	protected function buildOptionForUi( $aOptParams ) {

		$mCurrent = $aOptParams[ 'value' ];

		switch ( $aOptParams[ 'type' ] ) {

			case 'password':
				if ( !empty( $mCurrent ) ) {
					$mCurrent = '';
				}
				break;

			case 'array':

				if ( empty( $mCurrent ) || !is_array( $mCurrent ) ) {
					$mCurrent = [];
				}

				$aOptParams[ 'rows' ] = count( $mCurrent ) + 2;
				$mCurrent = stripslashes( implode( "\n", $mCurrent ) );

				break;

			case 'comma_separated_lists':

				$aNewValues = [];
				if ( !empty( $mCurrent ) && is_array( $mCurrent ) ) {

					foreach ( $mCurrent as $sPage => $aParams ) {
						$aNewValues[] = $sPage.', '.implode( ", ", $aParams );
					}
				}
				$aOptParams[ 'rows' ] = count( $aNewValues ) + 1;
				$mCurrent = implode( "\n", $aNewValues );

				break;

			case 'multiple_select':
				if ( !is_array( $mCurrent ) ) {
					$mCurrent = [];
				}
				break;

			case 'text':
				$mCurrent = stripslashes( $this->getMod()->getTextOpt( $aOptParams[ 'key' ] ) );
				break;
		}

		$aParams = [
			'value'    => is_scalar( $mCurrent ) ? esc_attr( $mCurrent ) : $mCurrent,
			'disabled' => !$this->getCon()->isPremiumActive() && ( isset( $aOptParams[ 'premium' ] ) && $aOptParams[ 'premium' ] ),
		];
		$aParams[ 'enabled' ] = !$aParams[ 'disabled' ];
		$aOptParams = array_merge( [ 'rows' => 2 ], $aOptParams, $aParams );

		// add strings
		try {
			$aOptStrings = $this->getMod()->getStrings()->getOptionStrings( $aOptParams[ 'key' ] );
			if ( is_array( $aOptStrings[ 'description' ] ) ) {
				$aOptStrings[ 'description' ] = implode( "<br/>", $aOptStrings[ 'description' ] );
			}
			$aOptParams = Services::DataManipulation()->mergeArraysRecursive( $aOptParams, $aOptStrings );
		}
		catch ( \Exception $oE ) {
		}
		return $aOptParams;
	}

	/**
	 * @param string $sId
	 * @return string
	 */
	protected function getHelpVideoUrl( $sId ) {
		return sprintf( 'https://player.vimeo.com/video/%s', $sId );
	}

	/**
	 * @param string $sSectionSlug
	 * @return array
	 */
	protected function getSectionNotices( $sSectionSlug ) {
		return [];
	}

	/**
	 * @param string $sSection
	 * @return array
	 */
	protected function getSectionWarnings( $sSection ) {
		return [];
	}
}