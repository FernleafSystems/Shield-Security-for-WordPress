<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-type OperatorChromeActionInput array{
 *   kind?:string,
 *   label?:string,
 *   type?:string,
 *   icon_class?:string,
 *   href?:string,
 *   ajax_action_json?:string,
 *   confirm_text?:string
 * }
 * @phpstan-type OperatorChromeAction array{
 *   kind:string,
 *   label:string,
 *   type:string,
 *   icon_class:string,
 *   href:string,
 *   ajax_action_json:string,
 *   confirm_text:string
 * }
 * @phpstan-type OperatorChromeDisplayOptionControlInput array{
 *   name?:string,
 *   label?:string,
 *   checked?:bool,
 *   disabled?:bool
 * }
 * @phpstan-type OperatorChromeDisplayOptionControl array{
 *   name:string,
 *   label:string,
 *   checked:bool,
 *   disabled:bool
 * }
 * @phpstan-type OperatorChromeDisplayOptionsInput array{
 *   title?:string,
 *   action_json?:string,
 *   controls?:list<OperatorChromeDisplayOptionControlInput>
 * }
 * @phpstan-type OperatorChromeDisplayOptions array{
 *   title:string,
 *   action_json:string,
 *   controls:list<OperatorChromeDisplayOptionControl>
 * }
 *
 * @phpstan-type OperatorChromeStepInput array{
 *   breadcrumb_label?:string,
 *   title?:string,
 *   summary?:string,
 *   focus?:string,
 *   next_step?:string,
 *   icon_class?:string,
 *   badge?:string,
 *   badge_status?:string,
 *   color_key?:string,
 *   actions?:list<OperatorChromeActionInput>,
 *   display_options?:OperatorChromeDisplayOptionsInput
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
 *   color_key:string,
 *   actions:list<OperatorChromeAction>,
 *   display_options:OperatorChromeDisplayOptions
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
 *   color_key?:string,
 *   actions?:list<OperatorChromeActionInput>,
 *   display_options?:OperatorChromeDisplayOptionsInput
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
 *   color_key:string,
 *   actions:list<OperatorChromeAction>,
 *   display_options:OperatorChromeDisplayOptions
 * }
 */
final class OperatorChromeContract {

	private const VALID_ACTION_KINDS = [
		'ajax',
		'href',
	];

	private const VALID_ACTION_TYPES = [
		'navigate',
		'update',
		'deactivate',
	];

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
			'actions'          => self::normalizeActions( $step[ 'actions' ] ?? [] ),
			'display_options'  => self::normalizeDisplayOptions( $step[ 'display_options' ] ?? [] ),
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
				'actions'          => $header[ 'actions' ] ?? [],
				'display_options'  => $header[ 'display_options' ] ?? [],
			] )
		);
	}

	/**
	 * @param OperatorChromeActionInput $action
	 * @return OperatorChromeAction
	 */
	public static function normalizeAction( array $action ) :array {
		$href = self::normalizeText( $action[ 'href' ] ?? '' );
		$ajaxActionJson = self::normalizeText( $action[ 'ajax_action_json' ] ?? '' );
		$kind = self::sanitizeActionKind( (string)( $action[ 'kind' ] ?? '' ) );
		if ( $kind === '' ) {
			$kind = $ajaxActionJson !== '' ? 'ajax' : 'href';
		}

		return [
			'kind'             => $kind,
			'label'            => self::normalizeText( $action[ 'label' ] ?? '' ),
			'type'             => self::sanitizeActionType( (string)( $action[ 'type' ] ?? '' ) ),
			'icon_class'       => self::normalizeText( $action[ 'icon_class' ] ?? '' ),
			'href'             => $kind === 'href' ? $href : '',
			'ajax_action_json' => $kind === 'ajax' ? $ajaxActionJson : '',
			'confirm_text'     => self::normalizeText( $action[ 'confirm_text' ] ?? '' ),
		];
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

	/**
	 * @param mixed $actions
	 * @return list<OperatorChromeAction>
	 */
	private static function normalizeActions( $actions ) :array {
		if ( !\is_array( $actions ) ) {
			return [];
		}

		return \array_values( \array_filter( \array_map(
			static function ( $action ) :array {
				return \is_array( $action )
					? self::normalizeAction( $action )
					: [];
			},
			$actions
		), static fn( array $action ) :bool => self::isRenderableAction( $action ) ) );
	}

	/**
	 * @param mixed $displayOptions
	 * @return OperatorChromeDisplayOptions
	 */
	private static function normalizeDisplayOptions( $displayOptions ) :array {
		if ( !\is_array( $displayOptions ) ) {
			return self::emptyDisplayOptions();
		}

		$normalized = [
			'title'       => self::normalizeText( $displayOptions[ 'title' ] ?? '' ),
			'action_json' => self::normalizeText( $displayOptions[ 'action_json' ] ?? '' ),
			'controls'    => \array_values( \array_filter( \array_map(
				static function ( $control ) :array {
					if ( !\is_array( $control ) ) {
						return [];
					}

					return [
						'name'     => self::sanitizeKey( (string)( $control[ 'name' ] ?? '' ) ),
						'label'    => self::normalizeText( $control[ 'label' ] ?? '' ),
						'checked'  => !empty( $control[ 'checked' ] ),
						'disabled' => !empty( $control[ 'disabled' ] ),
					];
				},
				\is_array( $displayOptions[ 'controls' ] ?? null ) ? $displayOptions[ 'controls' ] : []
			), static fn( array $control ) :bool => $control !== []
				&& $control[ 'name' ] !== ''
				&& $control[ 'label' ] !== '' ) ),
		];

		return $normalized[ 'title' ] !== ''
			&& $normalized[ 'action_json' ] !== ''
			&& !empty( $normalized[ 'controls' ] )
			? $normalized
			: self::emptyDisplayOptions();
	}

	/**
	 * @return OperatorChromeDisplayOptions
	 */
	private static function emptyDisplayOptions() :array {
		return [
			'title'       => '',
			'action_json' => '',
			'controls'    => [],
		];
	}

	/**
	 * @param array<string,string> $action
	 */
	private static function isRenderableAction( array $action ) :bool {
		if ( $action[ 'label' ] === '' ) {
			return false;
		}

		return $action[ 'kind' ] === 'ajax'
			? $action[ 'ajax_action_json' ] !== ''
			: $action[ 'href' ] !== '';
	}

	private static function normalizeText( $value ) :string {
		return \trim( (string)$value );
	}

	private static function sanitizeActionKind( string $kind ) :string {
		$kind = self::sanitizeKey( $kind );
		return \in_array( $kind, self::VALID_ACTION_KINDS, true )
			? $kind
			: '';
	}

	private static function sanitizeActionType( string $type ) :string {
		$type = self::sanitizeKey( $type );
		return \in_array( $type, self::VALID_ACTION_TYPES, true )
			? $type
			: 'navigate';
	}

	private static function sanitizeKey( string $key ) :string {
		if ( \function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $key );
		}

		$key = \strtolower( $key );
		return (string)\preg_replace( '/[^a-z0-9_\\-]/', '', $key );
	}
}
