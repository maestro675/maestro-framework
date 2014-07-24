<?php
/**
 * Description of Hooks
 *
 * @author maestro
 */
class Maestro_Hooks
{
    /**
	 * self instance
	 *
	 * @var Maestro_Hooks
	 */
	private static $_instance;

	const DEF_CLASS  = 'class';
	const DEF_FUNC   = 'function';
	const DEF_PARAMS = 'params';

    /**
	 *
	 * @var bool
	 */
	public $enabled = false;

	/**
	 *
	 * @var bool
	 */
	private $in_progress = false;

	/**
	 *
	 * @var array
	 */
	private  $hooks = array();

	/**
	 *
	 */
	public static function dump()
	{
		$instance = self::getInstance();
		pre($instance->hooks);
	}

	/**
	 * Инициализация параметров хуков
	 *
	 * @return void
	 */
	public static function init()
	{
		$instance = self::getInstance();
		if(!Maestro_App::getConfig()->System->enable_hooks)
		{
			return;
		}

		include_once Maestro_App::getConfig()->Path->settings.'hooks.php';

		if( !isset($hooks) || !is_array($hooks) )
		{
			return;
		}

		$instance->hooks   = $hooks;
		$instance->enabled = true;
	}

	/**
	 * Вызов хука (или массива хуков) по имени.
	 * Первый (обязательный) аргумент - имя хука, остальные аргументы - это
	 * параметры передаваемые в хук
	 *
	 * @return bool
	 */
	public static function invoke(/*..*/)
	{
		$instance = self::getInstance();

		if( !func_num_args() )
		{
			return false;
		}

		$args = func_get_args();
		$name = array_shift($args);

		if( !$instance->enabled || !isset($instance->hooks[$name]))
		{
			return false;
		}

		/*if( isset($instance->hooks[$name][0]) && is_array($instance->hooks[$name][0]) )
		{
			foreach( $instance->hooks[$name] as $hook )
			{
				$instance->_call_hook($hook);
			}
			return true;
		}
		else*/
		// если определен один хук, то возвращаем его результат
		{
			return $instance->_call_hook($instance->hooks[$name], $args);
		}
	}


	/**
	 * Выполнение хука непосредственно
	 *
	 * @param array $data
	 * @param array $parametres
	 * @return mixed
	 */
	private static function _call_hook($data, $parameters)
	{
		$instance = self::getInstance();

		if( !is_array($data))
		{
			return false;
		}

		if( $instance->in_progress )
		{
			return false;
		}

		$_class    = isset($data[self::DEF_CLASS])  ? $data[self::DEF_CLASS]  : false;
		$_function = isset($data[self::DEF_FUNC])   ? $data[self::DEF_FUNC]   : false;
		$_params   = isset($data[self::DEF_PARAMS]) ? $data[self::DEF_PARAMS] : null;

		if( !$_class && !$_function )
		{
			return false;
		}
		
		// run hook

		$instance->in_progress = true;

		$hook = new $_class();
		//$result = $hook->$_function($_params);
		$result = call_user_func_array(array($hook, $_function), $parameters);

		$instance->in_progress = false;

		return $result;
	}

	/**
	 *
	 */
	private function __construct () {}

	/**
	 *
	 */
	private function __clone () {}

	/**
	 * Return the single instance of object
	 *
	 * @return Maestro_Hooks
	 */
	public static function &getInstance()
	{
		if(!isset(self::$_instance))
		{
			self::$_instance = new self;
		}
		return self::$_instance;
	}
}
?>
