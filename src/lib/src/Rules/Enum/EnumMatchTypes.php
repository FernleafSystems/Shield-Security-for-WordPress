<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum;

class EnumMatchTypes {

	public const MATCH_TYPES = [
		self::MATCH_TYPE_EQUALS,
		self::MATCH_TYPE_IP_RANGE,
	];
	public const MATCH_TYPE_EQUALS = 'match_equals';
	public const MATCH_TYPE_EQUALS_I = 'match_exact_i';
	public const MATCH_TYPE_CONTAINS = 'match_contains';
	public const MATCH_TYPE_CONTAINS_I = 'match_contains_i';
	public const MATCH_TYPE_IP_EQUALS = 'match_ip_equals';
	public const MATCH_TYPE_IP_RANGE = 'match_ip_range';
	public const MATCH_TYPE_REGEX = 'match_regex';
	public const MATCH_TYPE_LESS_THAN = 'match_less_than';
	public const MATCH_TYPE_GREATER_THAN = 'match_greater_than';

	public static function MatchTypeNames() :array {
		return [
			self::MATCH_TYPE_CONTAINS     => __( 'Contains', 'wp-simple-firewall' ),
			self::MATCH_TYPE_CONTAINS_I   => sprintf( '%s (%s)', __( 'Contains', 'wp-simple-firewall' ), __( 'case-insensitive', 'wp-simple-firewall' ) ),
			self::MATCH_TYPE_EQUALS       => __( 'Equals', 'wp-simple-firewall' ),
			self::MATCH_TYPE_EQUALS_I     => sprintf( '%s (%s)', __( 'Equals', 'wp-simple-firewall' ), __( 'case-insensitive', 'wp-simple-firewall' ) ),
			self::MATCH_TYPE_IP_EQUALS    => __( 'IP Equals', 'wp-simple-firewall' ),
			self::MATCH_TYPE_IP_RANGE     => __( 'IP Range', 'wp-simple-firewall' ),
			self::MATCH_TYPE_REGEX        => __( 'Regular Expression', 'wp-simple-firewall' ),
			self::MATCH_TYPE_LESS_THAN    => __( 'Less Than', 'wp-simple-firewall' ),
			self::MATCH_TYPE_GREATER_THAN => __( 'Greater Than', 'wp-simple-firewall' ),
		];
	}

	public static function MatchTypesForStrings() :array {
		return [
			self::MATCH_TYPE_EQUALS,
			self::MATCH_TYPE_EQUALS_I,
			self::MATCH_TYPE_CONTAINS,
			self::MATCH_TYPE_CONTAINS_I,
			self::MATCH_TYPE_REGEX,
		];
	}

	public static function MatchTypesForIPs() :array {
		return [
			self::MATCH_TYPE_IP_EQUALS,
			self::MATCH_TYPE_IP_RANGE,
		];
	}

	public static function MatchTypesForNumbers() :array {
		return [
			self::MATCH_TYPE_EQUALS,
			self::MATCH_TYPE_LESS_THAN,
			self::MATCH_TYPE_GREATER_THAN,
		];
	}
}