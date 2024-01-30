<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum;

class EnumParameters {

	public const TYPE_ARRAY = 'array';
	public const TYPE_BOOL = 'bool';
	public const TYPE_CALLBACK = 'callback';
	public const TYPE_ENUM = 'enum';
	public const TYPE_INT = 'int';
	public const TYPE_IP_ADDRESS = 'ip_address';
	public const TYPE_SCALAR = 'scalar';
	public const TYPE_STRING = 'string';
	public const TYPE_URL = 'url';
	public const SUBTYPE_REGEX = 'regex';
}