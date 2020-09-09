<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class OverviewCards {

	use ModConsumer;

	public function buildForStatic() :array {
		return $this->buildForShuffle()[ 'sections' ];
	}

	public function buildForShuffle() :array {

		$stateDefs = [
			-2 => [
				'name' => __( 'Danger', 'wp-simple-firewall' ),
				'slug' => 'danger',
			],
			-1 => [
				'name' => __( 'Warning', 'wp-simple-firewall' ),
				'slug' => 'warning',
			],
			-0 => [
				'name' => __( 'Info', 'wp-simple-firewall' ),
				'slug' => 'info',
			],
			1  => [
				'name' => __( 'Good', 'wp-simple-firewall' ),
				'slug' => 'good',
			],
		];

		$stateNames = [];
		foreach ( $stateDefs as $stateKey => $stateDef ) {
			$stateNames[ $stateDef[ 'slug' ] ] = $stateDef[ 'name' ];
		}

		$allSections = [];
		$modGroups = [];
		$allStates = [];
		foreach ( $this->getCon()->modules as $mod ) {

			$modSections = $mod->getUIHandler()->getInsightsOverviewCards();

			foreach ( $modSections as $sectionKey => $section ) {
				if ( empty( $section[ 'cards' ] ) || !is_array( $section[ 'cards' ] ) ) {
					continue;
				}

				$section[ 'count' ] = count( $section[ 'cards' ] );

				foreach ( $section[ 'cards' ] as $key=> &$card ) {
					if ( empty( $card[ 'id' ] ) ) {
						$card[ 'id' ] = $key;
					}
					if ( empty( $card[ 'groups' ] ) || !is_array( $card[ 'groups' ] ) ) {
						$card[ 'groups' ] = [];
					}
					if ( !isset( $card[ 'state' ] ) ) {
						$card[ 'state' ] = 0;
					}

					$card[ 'mod' ] = $mod->getMainFeatureName();
					$card[ 'groups' ][ $mod->getSlug() ] = $mod->getMainFeatureName();

					// Translate state value (numeric) to text.
					$card[ 'groups' ][ $stateDefs[ $card[ 'state' ] ][ 'slug' ] ] = $stateDefs[ $card[ 'state' ] ][ 'name' ];
					$card[ 'state' ] = $stateDefs[ $card[ 'state' ] ][ 'slug' ];

					$allStates[] = $card[ 'state' ];
				}

				$modGroups[ $mod->getSlug() ] = $mod->getMainFeatureName();
				$allStates = array_unique( $allStates );

				$allSections[ $sectionKey ] = $section;
			}
		}

		return [
			'sections'    => $allSections,
			'mod_groups'  => $modGroups,
			'states'      => $allStates,
			'state_names' => $stateNames,
		];
	}
}