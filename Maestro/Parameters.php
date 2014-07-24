<?php
/**
 * Description of Parameters
 *
 * @author maestro
 */
class Maestro_Parameters extends Maestro_Definitions
{

	/**
	 * Массив значений параметров
	 *
	 * @var array
	 */
	protected $_vars = array();

	/**
	 * Инициализирует массив параметров значениями из входящего массива данных.
	 * Если задан массив `filter`, поля-ключи входящего массива, не найденные
	 * в значениях фильтра, игнорируются
	 *
	 * @param array|string $data Массив входящих данных
	 * @return Maestro_Parameters object
	 */
	public function init( $data = null, $filter = null )
	{
		if(!is_array($filter))
		{
			$filter = explode(',', $filter);
			foreach($filter as $i => $f)
			{
				$filter[$i] = trim($f);
			}
		}

		foreach( $this->_fields as $name => $info )
		{
			if(is_array($filter) && !in_array($name, $filter))
			{
				continue;
			}

			$value = null;
			if( isset( $data[$name] ) )
			{
				$value  = $data[$name];
			}
			else
			if(!array_key_exists($name, $this->_vars))
			{
				$value = $info[self::F_DEFAULT];
			}

			// выполняем метод инициализации - если определен
			$method = '_init'.ucfirst($name);
			if( method_exists( $this, $method ) )
			{
				$this->$method($value);
			}
			// иначе - просто присваивание значения
			else
			if( !is_null($value) )
			{
				$this->$name = $value;
			}
		}
		return $this;
	}


	/**
	 * Выводит массив параметров или отдельный параметр по имени
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function vars($name=null)
	{
		if( is_null( $name ) )
		{
			return $this->_vars;
		}
		elseif( isset( $this->_vars[$name] ) )
		{
			return $this->_vars[$name];
		}
		return false;
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
	 *
	 * @param string $name Имя параметра
	 * @param string $value Значение
	 * @return void
	 */
	public function __set($name, $value)
	{
		if( array_key_exists( $name, $this->_fields ) )
		{
			$this->_vars[$name] = $value;
		}
		else
		{
			throw new Exception(get_class().': field not exists ('.$name.')');
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

}
?>
