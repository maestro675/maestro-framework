<?php

/**
 * Description of Validator
 *
 * @author maestro
 */
class Maestro_Validator {

    /**
     *
     */
    public $last_message;

    /**
     *
     */
    protected $_fail_msg = array(
        'integer'  => 'ожидается числовое значение',
        'numeric'  => 'ожидается числовое значение',
        'datetime' => 'некорректная дата',
        'require'  => 'не должно быть пустым',
        'length'   => 'длина строки должна быть %s символов',
        'size'     => 'длина строки должна быть %s символов',
        'noempty'  => 'не должно быть пустым',
        'in'       => 'значение должно быть одним из (%s)',
        'out'      => 'значение НЕ должно быть одним из (%s)',
        'match'    => 'несоответствие формата',
        'strdate'  => 'дата должна быть в формате `ДД.ММ.ГГГГ`',
        'strdatetime' => 'время должно быть в формате `ДД.ММ.ГГГГ ЧЧ:ММ`',
        'more'     => 'должно быть больше %s',
        'less'     => 'должно быть меньше %s',
        'format'   => '',
		'sets'     => '',
		'width'    => '',
		'class'    => '',
		'numericOrNull' => 'numeric or null',
		'yaml'     => 'ошибка формата YAML'
    );

    /**
     *
     */
    protected $_last_fail_param;

    /**
     *
     */
    public function __construct() {

    }

    /**
     * Валидация значения `value` по указанным валидаторам, переданным
	 * в массиве `validators`
	 * 
     * @param mixed $value
     * @param array|string $validators
     */
    public function check($value, $validators) {
        if (!is_array($validators)) {
            $validators = array($validators);
        }
        foreach ($validators as $method => $params) {
            if (is_numeric($method)) {
                if (is_array($params)) {
                    throw new Maestro_Exception("'" . get_class() . ": invalid check structure");
                }
                $method = $params;
                $params = array();
            }
            if (!$this->_check($value, $method, $params)) {
                if (isset($this->_fail_msg[$method])) {
                    $this->last_message = sprintf($this->_fail_msg[$method], $this->_last_fail_param);
                } else {
                    $this->last_message = 'неверное значение';
                }
                return false;
            }
        }
        return true;
    }

    /**
     *
     * @param <type> $value
     * @param <type> $method
     * @param <type> $params
     * @return <type>
     */
    protected function _check($value, $method, $params) {
        $method = 'is_' . $method;
        if (method_exists($this, $method)) {
            return $this->$method($value, $params);
        } else {
            //throw new Maestro_Exception(get_class() . ': Unknown validator `' . $method . '`');
            //return false;
			return true;
        }
    }

    /**
     *
     * @param mixed $value
     * @return boolean
     */
    protected function is_link($value) {
        return true; //is_numeric( $value ) ;
    }

    /**
     *
     * @param mixed $value
     * @return boolean
     */
    protected function is_integer($value) {
        return is_numeric($value);
    }

    /**
     *
     * @param mixed $value
     * @return boolean
     */
    protected function is_numeric($value) {
        return is_numeric($value);
    }

    /**
     *
     * @param mixed $value
     * @return boolean
     */
	protected function is_numericOrNull($value) {
		return empty($value) ? true : is_numeric($value);
	}

    /**
     *
     * @param mixed $value
     * @return boolean
     */
    protected function is_integerOrNull($value) {
        return empty($value) ? true : is_numeric($value);
    }

    /**
     *
     * @param mixed $value
     * @return boolean
     */
    protected function is_datetime($value) {
        if (empty($value)) {
            return true;
        }
        return strtotime($value) ? true : false;
    }

    /**
     *
     * @param mixed $value
     * @return boolean
     */
    protected function is_require($value) {
        $value = trim($value);
        return (!is_null($value) && '' != $value );
    }

    /**
     *
     * @param mixed $value
     * @param integer|array $limit
     * @return boolean
     */
    protected function is_length($value, $limit) {
        if (!is_array($limit)) {
            $min = 0;
            $max = $limit;
        } else {
            $count = count($limit);
            if (!$count) {
                return true;
            } elseif (1 == $count) {
                $min = $max = $limit[0];
            } else {
                $min = $limit[0];
                $max = $limit[1];
            }
        }
        $len = mb_strlen($value, 'UTF-8');
        if ($len < $min) {
            $this->_last_fail_param = 'не менее ' . $min;
            return false;
        } elseif ($len > $max) {
            $this->_last_fail_param = 'не более ' . $max;
            return false;
        }
        return true;
    }

    /**
     *
     * @param mixed $value
     * @param integer|array $limit
     * @return boolean
     */
    protected function is_size($value, $limit) {
        return $this->is_length($value, $limit);
    }

    /**
     *
     * @param mixed $value
     * @param string|array $limit
     * @return boolean
     */
    protected function is_in($value, $list) {
        if (!is_array($list)) {
            $list = array($list);
        }
        $this->_last_fail_param = implode(',', $list);
        return in_array($value, $list);
    }

    /**
     *
     * @param mixed $value
     * @param string|array $limit
     * @return boolean
     */
    protected function is_keys($value, $list) {
        if (!is_array($list)) {
            $list = array($list);
        }
        $this->_last_fail_param = implode(',', $list);

        if (is_array($value)) {
            foreach ($value as $v => $name) {
                if (!array_key_exists($v, $list)) {
                    return false;
                }
            }
            return true;
        } else {
            return array_key_exists($value, $list);
        }
    }

    /**
     *
     * @param mixed $value
     * @param string|array $limit
     * @return boolean
     */
    protected function is_out($value, $list) {
        return!$this->is_in($value, $list);
    }

    /**
     *
     * @param string $value
     * @param string $match
     * @return boolean
     */
    protected function is_match($value, $match) {
        return preg_match($match, $value);
    }

    /**
     * Дата в виде строки `ДД.ММ.ГГГГ`
     *
     * @param string $value
     * @return boolean
     */
    protected function is_strdate($value) {
        if ($value && !$this->is_match($value, '/^[0-9]{2}[.][0-9]{2}[.][0-9]{4}$/')) {
            return false;
        }

        return $this->is_datetime($value);
    }

    /**
     * Дата в виде строки `ДД.ММ.ГГГГ`
     *
     * @param string $value
     * @return boolean
     */
    protected function is_strdatetime($value) {
        if ($value && !$this->is_match($value, '/^[0-9]{2}[.][0-9]{2}[.][0-9]{4}\\s[0-9]{2}:[0-9]{2}$/')) {
            return false;
        }

        return $this->is_datetime($value);
    }

    /**
     *
     * @param mixed $value
     * @param integer|string $limit
     * @return boolean
     */
    protected function is_more($value, $limit) {
        $this->_last_fail_param = $limit;
        if(is_numeric($value)) {
			$this->_fail_msg['more'] = 'Значение должно быть больше <b>' . $limit . '</b>';
            return (floatval($value) > $limit);
        } else {
			$this->_fail_msg['more'] = 'Длина строки должна быть больше <b>' . $limit . '</b> символов';
            return (mb_strlen($value) > $limit);
        }
    }

    /**
     *
     * @param mixed $value
     * @param integer|array $limit
     * @return boolean
     */
    protected function is_less($value, $limit) {
        $this->_last_fail_param = $limit;
	if(is_numeric($value)) {
            return ( floatval($value) < $limit );
	} else {
	    $this->_fail_msg['more'] = 'Длина строки должна быть меньше <b>' . $limit . '</b> символов';
            return (mb_strlen($value) < $limit);
	}
    }

    /**
     *
     * @param mixed $value
     * @return boolean
     */
    protected function is_noempty($value) {
        $value = trim($value);
        return (!empty($value) || $value);
    }

    /**
     *
     * @param mixed $value
     * @param string $format
     * @return boolean
     */
    protected function is_format($value, $format) {
        return true;
    }

    /**
     *
     * @param mixed $value
     * @param string $format
     * @return boolean
     */
    protected function is_nullable($value) {
        return true;
    }

    /**
     *
     * @param mixed $value
     * @param string $format
     * @return boolean
     */
    protected function is_sets($value) {
        return true;
    }

    /**
     *
     * @param mixed $value
     * @param string $format
     * @return boolean
     */
    protected function is_width($value) {
        return true;
    }

    /**
     *
     * @param mixed $value
     * @param string $format
     * @return boolean
     */
    protected function is_class($value) {
        return true;
    }

    /**
     *
     * @param mixed $value
     * @param string $format
     * @return boolean
     */
    protected function is_withall($value) {
        return true;
    }

    /**
     *
     * @param mixed $value
     * @param string $format
     * @return boolean
     */
    protected function is_placeholder($value) {
        return true;
    }

    /**
     *
     * @param mixed $value
     * @param string $format
     * @return boolean
     */
    protected function is_checked($value) {
        return true;
    }
	
	/**
	 * 
	 * @param string $value
	 * @return boolean
	 */
	protected function is_yaml($value) {
        $value = trim($value);
		if(empty($value)) {
			return true;
		}
		try {
			Maestro_Yaml::parse($value);
			return true;
		} catch(Exception $e) {
			return false;
		}
	}

}

?>
