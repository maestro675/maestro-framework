<?php
/**
 * Input: класс, реализующий параметризированную структуру данных.
 * Инициализирует массив параметров, проверяет корректность входящих данных.
 * К описанным и инициализированным параметрам обращение идет посредством `magic`
 * методов `_set` и `_get`, как через свойства класса.
 * 
 * В наследуемых классах можно реализовывать обработчики для присваивания значений:
 *     `_initName`, где `name` - имя ппараметра
 * и обрабочики типов параметров:
 *     `_checkTуpe`, где `type` - тип параметра
 * Публичные методы класса:
 *  - dump
 *  - clear
 *  - define
 *  - init
 *  - check
 *
 * @author Sergey Lysiansky, s.lysiansky@ucb-ua.com
 * @version    1.0
 */

class Maestro_Input
{
	const ID = 'Input class';
	/**
	 * Перечень типов параметров
	 */
	const DUMMY   = 'dummy';
	const NUMERIC = 'numeric';
	const INTEGER = 'integer';
	const STRING  = 'string';
	const DATE    = 'date';
	const TIME    = 'time';
	const INDEX   = 'index';
	const BOOLEAN = 'boolean';
	const UNIT    = 'unit';
	const FLAG    = 'flag';
	const ARR     = 'array';
	
	/**
	 * Перечень свойств параметра
	 */
	const FTYPE     = 'type';      # тип параметра
	const FSIZE     = 'size';      # размер
	const FDEFAULT  = 'default';   # значение по умолчанию
	const FEXTRA    = 'extra';      # имя параметра для получения значения из внешних источников (например, $_POST)
	const FFLAGS    = 'flags';
	const FCALLBACK = 'callback';
	
	const NOTNULL = 1; # флаг обязательного параметра
	const NOEMPTY = 2; # флаг непустого значения


	/**
	 * Массив описаний параметров модели
	 *
	 * @var array
	 */
	protected $_verbose = false;
	
	/**
	 * Массив описаний параметров модели
	 *
	 * @var array
	 */
	protected $_defines = array();
	
	/**
	 * Массив значений параметров
	 *
	 * @var array
	 */
	protected $_vars = array();

	/**
	 *
	 * @return <type> 
	 */
	public function vars()
	{
		return $this->_vars;
	}
	
	/**
	 * Выводит массив параметров
	 *
	 * @return void
	 */
	public function dump($name=null)
	{
		if( is_null( $name ) )
			pre( $this->_vars );
		else
		if( isset( $this->_vars[$name] ) )
			pre( $this->_vars[$name] );
	}
	
	/**
	 * Устанавливает признак отладки
	 *
	 * @param boolean $flag Признак отладки
	 * @return void
	 */
	public function verbose($flag)
	{
		$this->_verbose = (bool)$flag;
	}
	
	/**
	 * Обнуляет массив значений
	 *
	 * @return void
	 */
	public function clear()
	{
		$this->_vars = array();		
	}
	
	/**
	 * Устанавливает описание одного параметра.
	 * Имя загрузки 'from' используется в методах load* для поиска.
	 *
	 *
	 * @param array|string $name Наименование параметра или массив из имени параметра и имени загрузки
	 * @param string $type Тип параметра
	 * @param string $default Значение по умолчанию
	 * @param string $is_require Признак обязательного параметра
	 * @return void
	 */
	public function define($names, $type=self::DUMMY, $default=null, $flags=0, $fsize=0, $callback='')
	{
		if( !is_array( $names ) )
		{
			$names = array( $names );
		}

		$info = array(
			self::FTYPE     => $type,
			self::FDEFAULT  => $default,
			self::FEXTRA    => null,
			self::FSIZE     => $fsize,
			self::FFLAGS    => $flags,
			self::FCALLBACK => $callback
		);
		
		foreach($names as $name=>$extra)
		{
			if(is_numeric($name))
			{
				$name = $extra;
			}			
		
			$this->_defines[$name] = $info;
			$this->_defines[$name][self::FEXTRA] = $extra;
			$this->_vars[$name] = null;
		}
	}

    /**
     *
     * @param <type> $m
     * @param <type> $a 
     */
    public function __call( $name, $args )
    {
		if( preg_match( '/^(get|set)(\w+)/', strtolower( $name ), $match ))
        {
            $var = $match[2];
            if( isset( $this->_vars[$var] ) )
            {
                if( 'get' == $match[1] )
                {
                    return $this->_vars[$var];
                }
                else
                {
                    $this->_vars[$var] = $args[0];
                }
            }
        }
    }
	
	/**
	 * `Magic` метод, устанавливающий значение параметра
	 * Если существует метод класса `_set<Name>`, где <Name> - имя параметра с заглавной
	 * первой буквой, то он вызывается для инициализации параметра, иначе - просто
	 * присваивается значение
	 *
	 * @param string $name Имя параметра
	 * @param string $value Значение
	 * @return void
	 */
	public function __set($name, $value)
	{
		if( array_key_exists( $name, $this->_defines ) )
		{
			$this->_vars[$name] = $value;
		}
	}
	
	/**
	 * `Magic` метод получения значение параметра
	 *
	 * @param string $name Имя параметра
	 * @return mixed
	 */
	public function __get($name)
	{
        if( isset( $this->_vars[$name] ) )
		{
			return $this->_vars[$name];
		}
		else
			return null;
	}

	/**
	 *
	 * @return boolean
	 */
	public function initpost()
	{
		return $this->init( $_POST );
	}
	
	/**
	 * Инициализирует массив параметров значениями из входящего массива данных.
	 * Если входящие данные отсутствуют - переменные инициализируются значениями по умолчанию.
	 *
	 * @param array $inputs Массив входящих данных
	 * @return boolean 
	 */
	public function init( $inputs = null )
	{
		foreach( $this->_defines as $name => $info )
		{
			//$this->_vars[$name] = $this->_defines[$name][self::FDEFAULT];

			$getname  = $this->_defines[$name][self::FEXTRA];
			$flags    = $this->_defines[$name][self::FFLAGS];
			$callback = $this->_defines[$name][self::FCALLBACK];
			
			$value = null;
			if( isset( $inputs[$getname] ) )
			{
				$value  = trim(htmlspecialchars($inputs[$getname]));
			}

			// выполняем метод инициализации - если определен
			$method = '_init'.ucfirst($name); 
			if( method_exists( $this, $method ) )
			{
				//$this->trigger(self::ID.": Call method `{$method}` with value `{$value}`");
				$this->$method($value);
			}
			// иначе - просто присваивание значения
			else
			if( !is_null($value) )				
			{
				$this->$name = $value;						
			}
			if($name=='enable') pre($value);
		}
		return true;
	}
	
	/**
	 * Определяет полноту и валидность установленных параметров
	 *
	 * @return boolean
	 */
	public function check( $verbose=false )
	{
		$is_valid = true;
		$this->_verbose = $verbose;
		// пройдемся по массиву опеделений параметров
		foreach( $this->_defines as $name => $info )
		{
			// получаем значение параметра
			$value = $this->_vars[$name];
			$flags   = $this->_defines[$name][self::FFLAGS];
			
			// если параметр не задан, или задан пустым с соотв.флагом (NOEMPTY) - по умолчанию
			if( is_null( $value ) || ( empty( $value ) && ( $info['flags'] & self::NOEMPTY ) ) )
			{
				$value = $info[self::FDEFAULT];
				$this->$name = $value;
			}
			
			// проверка на пустое значение
			if( ( empty( $value ) || !$value ) && ( $info['flags'] & self::NOEMPTY ) )
			{
				$this->trigger( self::ID.".Check error: Value of `{$name}` is required. " );
				$is_valid = false;
				
			}

			// проверка на ненулевое значение
			if( is_null( $value ) && ( $info['flags'] & self::NOTNULL ) )
			{
				$this->trigger( self::ID.".Check error: Value of `{$name}` is required. " );
				$is_valid = false;

			}
			
			// пытаемся найти метод для проверки значения параметра, соответствующий типу
			$method = '_check'.ucfirst( $this->_defines[$name][self::FTYPE] );
			if( method_exists( $this, $method) )
			{
				$res = $this->$method( $name, $value );
				if( !$res )
				{
					$is_valid = false;
					$this->trigger( self::ID.".Check error: Value of `{$name}` not valid: ".$value );
				}
			}
		}
		return $is_valid;
	}

	
	
	private function _checkDummy( $name, $value )
	{
		return true;
	}
	
	private function _checkNumeric( $name, $value )
	{
		return is_numeric($value);
	}
	
	private function _checkInteger( $name, $value )
	{
		return is_numeric($value);
	}
	
	private function _checkIndex( $name, $value )
	{
		return (is_numeric($value) && $value>=0);
	}

	private function _checkDate( $name, $value )
	{
		if( preg_match( '/^[0-9]{2}[.][0-9]{2}[.][0-9]{4}$/', $value ) 
		 || preg_match( '/^[0-9]{2}[.][0-9]{2}[.][0-9]{4} [0-9]{2}:[0-9]{2}$/', $value )
		 || preg_match( '/^[0-9]{2}[.][0-9]{2}[.][0-9]{4} [0-9]{2}:[0-9]{2}:[0-9]{2}$/', $value )
		)
		{
			return (boolean) strtotime( $value );
		}
		return false;
	}

	private function _checkArr( $name, $value )
	{
		return is_array( $value );
	}
	
	private function _checkString( $name, $value )
	{
		return true;
	}
	
	private function _checkBoolean( $name, $value )
	{
		$f = ( isset( $this->_vars[$name] ) && $this->_vars[$name] );
        $this->_vars[$name] = $f ? 1 : 0;
        return true;
	}

	private function _checkFlag( $name, $value )
	{
		$this->$name = isset( $this->_vars[$name] ) ? 1 : 0;
		return true;
	}

	private function _checkUnit( $name, $value )
	{
		return ( $this->_vars[$name] instanceof Maestro_Unit );
	}


	
	private function trigger( $str )
	{
		
		if( $this->_verbose )
		{
			throw new Exception( $str, E_ERROR );
		}
		else
		{
			throw new Exception('Invalid input data.');
		}
	}
}
