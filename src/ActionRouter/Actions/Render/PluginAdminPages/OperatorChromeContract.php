<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-type OperatorChromeStepInput array{
 *   breadcrumb_label?:string,
 *   title?:string,
 *   summary?:string,
 *   focus?:string,
 *   next_step?:string,
 *   icon_class?:string,
 *   badge?:string,
 *   badge_status?:string,
 *   color_key?:string
 * }
 * @phpstan-type OperatorChromeStep array{
 *   breadcrumb_label:string,
 *   title:string,
 *   summary:string,
 *   focus:string,
 *   next_step:string,
 *   icon_class:string,
 *   badge:string,
 *   badge_status:string,
 *   color_key:string
 * }
 * @phpstan-type DrillLayerHeaderInput array{
 *   compact_back_label?:string,
 *   active_back_label?:string,
 *   breadcrumb_label?:string,
 *   title?:string,
 *   meta?:string,
 *   summary?:string,
 *   focus?:string,
 *   next_step?:string,
 *   icon_class?:string,
 *   badge?:string,
 *   badge_status?:string,
 *   color_key?:string
 * }
 * @phpstan-type DrillLayerHeader array{
 *   compact_back_label:string,
 *   active_back_label:string,
 *   breadcrumb_label:string,
 *   title:string,
 *   meta:string,
 *   summary:string,
 *   focus:string,
 *   next_step:string,
 *   icon_class:string,
 *   badge:string,
 *   badge_status:string,
 *   color_key:string
 * }
 */
final class OperatorChromeContract {

	private const VALID_STATUSES = [
		'good',
		'warning',
		'critical',
		'info',
		'neutral',
	];

	private const VALID_COLOR_KEYS = [
		'home',
		'actions',
		'configure',
		'investigate',
		'reports',
		'critical',
		'warning',
		'good',
		'info',
		'neutral',
	];

	/**
	 * @param OperatorChromeStepInput $step
	 * @return OperatorChromeStep
	 */
	public static function normalizeStep( array $step ) :array {
		$badgeStatus = self::sanitizeStatus( (string)( $step[ 'badge_status' ] ?? '' ) );
		$colorKey = self::sanitizeColorKey( (string)( $step[ 'color_key' ] ?? $badgeStatus ) );

		return [
			'breadcrumb_label' => self::normalizeText( $step[ 'breadcrumb_label' ] ?? '' ),
			'title'            => self::normalizeText( $step[ 'title' ] ?? '' ),
			'summary'          => self::normalizeText( $step[ 'summary' ] ?? '' ),
			'focus'            => self::normalizeText( $step[ 'focus' ] ?? '' ),
			'next_step'        => self::normalizeText( $step[ 'next_step' ] ?? '' ),
			'icon_class'       => self::normalizeText( $step[ 'icon_class' ] ?? '' ),
			'badge'            => self::normalizeText( $step[ 'badge' ] ?? '' ),
			'badge_status'     => $badgeStatus,
			'color_key'        => $colorKey,
		];
	}

	/**
	 * @param DrillLayerHeaderInput $header
	 * @return DrillLayerHeader
	 */
	public static function normalizeHeader( array $header ) :array {
		return \array_merge(
			[
				'compact_back_label' => self::normalizeText( $header[ 'compact_back_label' ] ?? '' ),
				'active_back_label'  => self::normalizeText( $header[ 'active_back_label' ] ?? '' ),
				'meta'               => self::normalizeText( $header[ 'meta' ] ?? '' ),
			],
			self::normalizeStep( [
				'breadcrumb_label' => $header[ 'breadcrumb_label' ] ?? '',
				'title'            => $header[ 'title' ] ?? '',
				'summary'          => $header[ 'summary' ] ?? '',
				'focus'            => $header[ 'focus' ] ?? '',
				'next_step'        => $header[ 'next_step' ] ?? '',
				'icon_class'       => $header[ 'icon_class' ] ?? '',
				'badge'            => $header[ 'badge' ] ?? '',
				'badge_status'     => $header[ 'badge_status' ] ?? '',
				'color_key'        => $header[ 'color_key' ] ?? ( $header[ 'badge_status' ] ?? '' ),
			] )
		);
	}

	public static function sanitizeStatus( string $status ) :string {
		$status = self::sanitizeKey( $status );
		return \in_array( $status, self::VALID_STATUSES, true )
			? $status
			: 'neutral';
	}

	public static function sanitizeColorKey( string $colorKey ) :string {
		$colorKey = self::sanitizeKey( $colorKey );
		return \in_array( $colorKey, self::VALID_COLOR_KEYS, true )
			? $colorKey
			: 'neutral';
	}

	public static function encodeJson( array $payload ) :string {
		return (string)( \json_encode( $payload ) ?: '' );
	}

	private static function normalizeText( $value ) :string {
		return \trim( (string)$value );
	}

	private static function sanitizeKey( string $key ) :string {
		if ( \function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $key );
		}

		$key = \strtolower( $key );
		return (string)\preg_replace( '/[^a-z0-9_\\-]/', '', $key );
	}
}
