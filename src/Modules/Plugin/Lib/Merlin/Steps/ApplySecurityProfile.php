<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield\Profiles\CompareLevelWithCurrent;
use FernleafSystems\Wordpress\Plugin\Shield\Profiles\Levels;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Response;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\ErrorLog;

class ApplySecurityProfile extends Base {

	public const SLUG = 'apply_security_profile';

	public function processStepFormSubmit( array $form ) :Response {
		$resp = parent::processStepFormSubmit( $form );
		$con = self::con();

		$level = \strtolower( $form[ 'security_profile' ] ?? '' );
		if ( empty( $level ) ) {
			// Nothing was chosen, so there's nothing to apply: saying it was done would be untrue.
			$resp->success = false;
			$resp->message = $resp->error = __( 'Choose a protection level to apply, or skip this step.', 'wp-simple-firewall' );
		}
		else {
			try {
				$con->comps->security_profiles->applyLevel( $level );
				$resp->success = true;
				/* translators: %s: name of a protection level */
				$resp->message = sprintf( __( "Profile '%s' was applied.", 'wp-simple-firewall' ), $con->comps->security_profiles->meta( $level )[ 'title' ] );
			}
			catch ( \Exception $e ) {
				ErrorLog::write( $e->getMessage() );
				$resp->success = false;
				$resp->message = $resp->error = __( 'An unsupported profile was selected', 'wp-simple-firewall' );
			}
		}
		return $resp;
	}

	public function getName() :string {
		return __( 'Security Profile', 'wp-simple-firewall' );
	}

	/**
	 * Each level is shown with what it would change on this site, so choosing one is a comparison of the site as
	 * it is with the site as it would be, rather than of one level's list against another's. The recommended
	 * level starts chosen, but only while it would make nothing on this site less strict: a level that loosens
	 * what the site already has is never the one put forward, so then nothing starts chosen.
	 */
	protected function getStepRenderData() :array {
		$profiles = self::con()->comps->security_profiles;

		$levels = [];
		foreach ( Levels::Enum() as $level ) {
			$comparison = ( new CompareLevelWithCurrent() )->build( $level );
			$changes = $comparison[ 'stricter' ] + $comparison[ 'looser' ];
			$title = $profiles->meta( $level )[ 'title' ];
			$levels[ $level ] = [
				'key'         => $level,
				'title'       => $title,
				'subtitle'    => $profiles->meta( $level )[ 'subtitle' ],
				'changes'     => $changes,
				'comparison'  => $comparison,
				'strings'     => [
					'changes'  => $changes === 0
						? __( 'Matches your site now', 'wp-simple-firewall' )
						/* translators: %s: number of settings */
						: sprintf( _n( '%s setting changes', '%s settings change', $changes, 'wp-simple-firewall' ), $changes ),
					// Said on the level's own card, so a level that loosens the site is plain before it's chosen.
					'loosens'  => $comparison[ 'looser' ] === 0 ? ''
						/* translators: %s: number of settings */
						: sprintf( _n( 'Makes %s setting less strict', 'Makes %s settings less strict', $comparison[ 'looser' ], 'wp-simple-firewall' ), $comparison[ 'looser' ] ),
					/* translators: %s: name of a protection level */
					'compared' => sprintf( __( '%s compared with your site now', 'wp-simple-firewall' ), $title ),
					'apply'    => $changes === 0
						? __( 'Keep my settings as they are', 'wp-simple-firewall' )
						: sprintf(
						/* translators: %1$s: name of a protection level, %2$s: "5 changes" */
							__( 'Apply %1$s (%2$s)', 'wp-simple-firewall' ),
							$title,
							/* translators: %s: number of changes */
							sprintf( _n( '%s change', '%s changes', $changes, 'wp-simple-firewall' ), $changes )
						),
				],
			];
		}

		$recommended = $levels[ Levels::MEDIUM ][ 'comparison' ][ 'looser' ] === 0 ? Levels::MEDIUM : '';

		return [
			'strings' => [
				'step_title'   => __( 'Choose A Protection Level', 'wp-simple-firewall' ),
				'intro'        => __( 'A level sets many options at once. Nothing changes until you apply it, and you can adjust any setting later in Configure.', 'wp-simple-firewall' ),
				'levels_label' => __( 'Protection level', 'wp-simple-firewall' ),
				'recommended'  => __( 'Recommended to get started', 'wp-simple-firewall' ),
				'choose_first' => __( 'Choose a level to apply', 'wp-simple-firewall' ),
				/* translators: %s: number of settings */
				'stricter'     => __( '%s stricter', 'wp-simple-firewall' ),
				/* translators: %s: number of settings */
				'looser'       => __( '%s less strict', 'wp-simple-firewall' ),
				/* translators: %s: number of settings */
				'unchanged'    => __( '%s unchanged', 'wp-simple-firewall' ),
				'show_unchanged' => __( 'Show or hide the settings that stay the same', 'wp-simple-firewall' ),
				'col_setting'  => __( 'Setting', 'wp-simple-firewall' ),
				'col_now'      => __( 'Current', 'wp-simple-firewall' ),
				'col_after'    => __( 'New', 'wp-simple-firewall' ),
				// How each row's change reads, by the change the comparison gives it.
				'change_labels' => [
					'stricter'    => __( 'Stricter', 'wp-simple-firewall' ),
					'looser'      => __( 'Less strict', 'wp-simple-firewall' ),
					'same'        => __( 'No change', 'wp-simple-firewall' ),
					'unavailable' => __( 'Not on your plan', 'wp-simple-firewall' ),
				],
				'all_match'    => __( 'Your site already matches this level.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'levels'      => $levels,
				'recommended' => $recommended,
			],
		];
	}
}
