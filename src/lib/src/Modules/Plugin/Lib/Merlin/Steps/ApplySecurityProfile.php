<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield\Profiles\Levels;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Response;

class ApplySecurityProfile extends Base {

	public const SLUG = 'apply_security_profile';

	public function processStepFormSubmit( array $form ) :Response {
		$resp = parent::processStepFormSubmit( $form );
		$con = self::con();

		$level = \strtolower( $form[ 'security_profile' ] ?? '' );
		if ( empty( $level ) ) {
			$resp->success = true;
			$resp->message = __( 'No profile was applied' );
		}
		else {
			try {
				$con->comps->security_profiles->applyLevel( $level );
				$resp->success = true;
				$resp->message = sprintf( __( "Profile '%s' was applied.", 'wp-simple-firewall' ), $con->comps->security_profiles->meta( $level )[ 'title' ] );
			}
			catch ( \Exception $e ) {
				error_log( $e->getMessage() );
				$resp->success = false;
				$resp->message = $resp->error = __( 'An unsupported profile was selected' );
			}
		}
		return $resp;
	}

	public function getName() :string {
		return __( 'Security Profile', 'wp-simple-firewall' );
	}

	protected function getStepRenderData() :array {
		$con = self::con();
		$secProfiles = $con->comps->security_profiles;
		return [
			'strings' => [
				'step_title' => __( 'Apply Ready-Made Security Profile', 'wp-simple-firewall' ),
			],
			'imgs'    => [
				'svgs' => [
					'shield_x'           => $con->svgs->raw( 'shield-x.svg' ),
					'shield_check'       => $con->svgs->raw( 'shield-check.svg' ),
					'shield_exclamation' => $con->svgs->raw( 'shield-fill-exclamation.svg' ),
				],
			],
			'vars'    => [
				'profile_structure' => $secProfiles->getStructure(),
				'profile_levels'    => [
					Levels::CURRENT   => \array_merge(
						$secProfiles->meta( Levels::CURRENT ),
						[
							'structure' => $secProfiles->buildForCurrent(),
						],
					),
					Levels::LIGHT   => \array_merge(
						$secProfiles->meta( Levels::LIGHT ),
						[
							'structure' => $secProfiles->buildForLevel( Levels::LIGHT ),
						],
					),
					Levels::MEDIUM  => \array_merge(
						$secProfiles->meta( Levels::MEDIUM ),
						[
							'structure' => $secProfiles->buildForLevel( Levels::MEDIUM ),
						],
					),
					Levels::STRONG  => \array_merge(
						$secProfiles->meta( Levels::STRONG ),
						[
							'structure' => $secProfiles->buildForLevel( Levels::STRONG ),
						],
					),
				],
			],
		];
	}
}