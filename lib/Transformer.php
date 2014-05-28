<?php

namespace Amp\Transformers;

class Transformer
{
	const APP = 'application';
	const EXT = 'external';

	const DEFINITION_KEY = 'key';
	const DEFINITION_FUNC = 'func';
	const DEFINITION_ARGS = 'args';

	/** @var Utility Utility object. */
	protected $utility;

	/** @var array Transformation definitions */
	private $definitions = [];

	/**
	 * @param Utility $utility Utility object.
	 */
	public function __construct(Utility $utility)
	{
		$this->utility = $utility;

		$this->definitions();
	}

	/**
	 * Saves field definitions.
	 *
	 * @param string   $app_name     Property name in application context.
	 * @param string   $storage_name Property name storage context.
	 * @param callable $app_func     Callable for transforming property to app context.
	 * @param callable $storage_func Callable for transforming property to storage context.
	 * @param array    $app_args     Arguments for app callback.
	 * @param array    $storage_args Arguments for storage callback.
	 */
	public function define(
		$app_name,
		$storage_name,
		callable $app_func = null,
		callable $storage_func = null,
		$app_args = [],
		$storage_args = []
	) {
		$this->definitions[self::EXT][$app_name]     = [
			self::DEFINITION_KEY  => $storage_name,
			self::DEFINITION_FUNC => $storage_func,
			self::DEFINITION_ARGS => $storage_args
		];
		$this->definitions[self::APP][$storage_name] = [
			self::DEFINITION_KEY  => $app_name,
			self::DEFINITION_FUNC => $app_func,
			self::DEFINITION_ARGS => $app_args,
		];
	}

	/**
	 * Defines a date property.
	 *
	 * App format: DateTime object
	 * Storage format: YYYY-MM-DD HH:MM:SS
	 *
	 * @param string $app_name     Property name in application context.
	 * @param string $storage_name Property name storage context.
	 */
	public function defineDate($app_name, $storage_name)
	{
		$this->define($app_name, $storage_name, 'date_create', [$this->utility, 'convertDateToMysql']);
	}

	/**
	 * Defines a property that is stored as JSON. Expands to an array in app.
	 *
	 * @param string $app_name     Property name in application context.
	 * @param string $storage_name Property name storage context.
	 */
	public function defineJson($app_name, $storage_name)
	{
		$this->define($app_name, $storage_name, 'json_decode', 'json_encode', [true]);
	}

	/**
	 * @param string $app_name     Property name in application context.
	 * @param string $storage_name Property name storage context.
	 * @param array  $mask
	 */
	public function defineMask($app_name, $storage_name, $mask)
	{
		$mask_flip = array_flip($mask);

		$this->define(
			$app_name,
			$storage_name,
			[$this->utility, 'bitmask'],
			[$this->utility, 'bitmask'],
			[$mask],
			[$mask_flip]
		);
	}

	/**
	 * Transforms data for app.
	 *
	 * @param array $data Data for transformation.
	 * @param null  $key
	 * @param bool  $array
	 * @return array
	 */
	public function toApp($data, $key = null, $array = false)
	{
		return $this->to(self::APP, $data, $key, $array);
	}

	/**
	 * Transforms data for storage.
	 *
	 * @param array $data Data for transformation.
	 * @param null  $key
	 * @param bool  $array
	 * @return array
	 */
	public function toExt($data, $key = null, $array = false)
	{
		return $this->to(self::EXT, $data, $key, $array);
	}

	/**
	 * Transforms data for a specific environment.
	 *
	 * @param string      $env  Environment name.
	 * @param array       $data Data for transformation.
	 * @param null|string $key
	 * @param bool        $array
	 * @return array
	 */
	public function to($env, $data, $key = null, $array = false)
	{
		if (!in_array($env, [self::APP, self::EXT])) {
			throw new \InvalidArgumentException('Unknown environment: ' . $env);
		}

		// Handle arrays by recursion
		if ($array) {
			$map = function ($data) use ($env, $key) {
				return $this->to($env, $data, $key);
			};

			return array_map($map, $data);
		}

		// Handle keys
		if (!is_null($key)) {
			if (!$def = $this->getDefinition($env, $key)) {
				throw new \InvalidArgumentException('Unknown key: ' . $key);
			}

			return $this->parseDefinitionValue($def, $data);
		}

		// Transform a data set
		$ret = [];
		foreach ($data as $key => $value) {
			if ($def = $this->getDefinition($env, $key)) {
				$ret[$def[self::DEFINITION_KEY]] = $this->parseDefinitionValue($def, $value);
			}
		}

		$after = 'after' . ucfirst($env);
		$ret   = $this->$after($ret);

		return $ret;
	}

	public function getKeysApp()
	{
		return $this->getKeys(self::APP);
	}

	public function getKeysExt()
	{
		return $this->getKeys(self::EXT);
	}

	public function getKeys($env)
	{
		if (!in_array($env, [self::APP, self::EXT])) {
			throw new \InvalidArgumentException('Unknown environment: ' . $env);
		}

		$env = $env === self::APP ? self::EXT : self::APP;

		return array_keys($this->definitions[$env]);
	}

	/**
	 * No-op. Can be used in subclass to setup definitions.
	 */
	protected function definitions()
	{

	}

	protected function afterApplication($data)
	{
		return $data;
	}

	protected function afterExternal($data)
	{
		return $data;
	}

	/**
	 * Gets a property definition by key for a specific environment.
	 *
	 * @param string $env Environment name.
	 * @param string $key Key name.
	 * @return array|null
	 */
	private function getDefinition($env, $key)
	{
		return isset($this->definitions[$env][$key])
			? $this->definitions[$env][$key]
			: null;
	}

	/**
	 * Parses a value based on a definition.
	 *
	 * @param array $definition Definition array.
	 * @param mixed $value      Value.
	 * @return mixed
	 */
	private function parseDefinitionValue($definition, $value)
	{
		if (is_callable($definition[self::DEFINITION_FUNC])) {
			array_unshift($definition[self::DEFINITION_ARGS], $value);
			$value = call_user_func_array($definition[self::DEFINITION_FUNC], $definition[self::DEFINITION_ARGS]);
		}

		return $value;
	}
}
