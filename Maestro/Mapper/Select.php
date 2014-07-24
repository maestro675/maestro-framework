<?php
/**
 * Description of Maestro_Mapper_Select
 *
 * В конструктор параметром передается ссылка на экземпляр класса описания
 * полей Maestro_Mapper_Defines (или наследуемого класса) - свойство $_defines.
 *
 * @author maestro
 */
class Maestro_Mapper_Select extends Maestro_Select
{
	/**
	 * Объект описаний полей и таблицы БД
	 *
	 * @var Maestro_Mapper_Defines
	 */
	protected $_defines;

	/**
	 * constructor
	 *
	 * @param Maestro_Mapper_Defines $defines описания полей
	 */
	public function __construct( $defines )
	{
		parent::__construct();
        $this->_defines = $defines;
	}

	/**
	 * Добавляет поля к запросу
	 * Параметр $cols может быть строкой или массивом. В последнем случае, если элемент массива
	 * задан
	 *
	 * @param array|string $cols Массив столбцов
	 */
	protected function _columns( $tableAlias, $cols )
	{
        if( !is_array( $cols ) )
			$cols = array( $cols );

        foreach( array_filter( $cols ) as $alias => $col )
		{
            $originAlias       = $alias;
			$currentTableAlias = $tableAlias;
			// выражения добавляем без лишних вопросов
			if( preg_match('/^EXPR(.+)$/i', $col, $m ) )
			{
				$currentTableAlias = null;
				//$alias = $aliasName;
				$col = trim( $m[1] );
			}
			else
			{
                if (preg_match('/^(.+)\s+AS\s+(.+)$/i', $col, $m))
				{
					$col = $m[1];
                    $alias = $m[2];
                }

				$col = strtoupper( $col );
				$lcol = strtolower( $col );

				// если поле есть в описаниях полей
				$fieldInfo = $this->_defines->getField($lcol);
				if( $this->_defines->tableAlias == $currentTableAlias && !is_null($fieldInfo) )
				{
					// если поле принадлежит основной таблице объектов
					if( strtoupper( $this->_defines->tableName ) == strtoupper( $fieldInfo[Maestro_Mapper_Defines::F_TABLE] ) )
					{
						$currentTableAlias = $this->_defines->tableAlias;
						$alias = null;
						$col = $fieldInfo[Maestro_Mapper_Defines::F_TRUENAME];
					}
					// если поле - из дополнительных свойств
					else
					{
						$currentTableAlias = $this->_uniqueAlias();
						$this->_property_join( $currentTableAlias,  $fieldInfo );
						$alias = $col;
						$col = 'VAL';
					}

					// если поле - идентификатор другого объекта и разрешено линкование
					//if($fieldInfo['type']=='link')
					if( isset($this->_defines->linked[$lcol]) )
					{
						$_link_talias  = $this->_uniqueAlias('ptr');
						$this->_link_join($_link_talias, $fieldInfo, $currentTableAlias, $col, $lcol);
					}
				}
				else
				// добавляем поля из обычных присоединенных таблиц (если это не линки)
				if( !preg_match('/^LINK__(.+)$/i', $alias ) )
				{
					if(is_string($alias))
					{
						$alias = 'JOINED__'.$alias;
					}
					else
					{
						$alias = 'JOINED__'.$col;
					}
				}
			}
            $this->_parts[self::COLUMNS][] = array(
				'talias' => $currentTableAlias,
				'name' => $col,
				'alias' => is_string( $alias ) ? $alias : null
			);
			$_a =  is_string($alias) ? $alias : $lcol;
			$_a = str_replace('JOINED__', '', $_a);
			$_a = strtolower($_a);
			$this->_aliases[$_a] = $currentTableAlias.'.'.$col;
        }
	}

	/**
	 * Присоединяет к выборке поля объекта, на который ссылается поле $field
	 *
	 * @param string $tableAlias алиас присоединяемой таблицы
	 * @param array $field описание поля-линка основной таблицы
	 * @param string $sourceTableAlias алиас таблицы поля-линка
	 * @param string $sourceCol имя поля-линка в представлении SQL-запроса
	 */
	protected function _link_join( $tableAlias, $field, $sourceTableAlias, $sourceCol, $sourceAlias )
	{
		$_link_classid = $field[Maestro_Mapper_Defines::F_CHECK]['link'];
		$_link_alias   = $sourceAlias ? $sourceAlias : $sourceCol;
		$_link_prefix  = 'LINK__'.$_link_alias.'__';

		// список полей прилинковываемого объекта
		$_link_fields = array();
		$_fields = $this->_defines->linked[strtolower($_link_alias)];
		foreach($_fields as $_field)
		{
			$_key = $_link_prefix.$_field;
			$_link_fields[$_key] = $_field;
		}

		$this->_join(self::LEFT_JOIN,
				array($tableAlias => 'OBJECTS'),
				sprintf('%s.CLASSID=%d and %s.DELETED=0 and %s.OBID=%s.%s',
						$tableAlias,
						$_link_classid,
						$tableAlias,
						$tableAlias,
						$sourceTableAlias,
						$sourceCol
				),
				$_link_fields
		);
	}

	/**
	 * Генерирует и возвращает уникальный алиас для таблицы
	 *
	 * @param string $prefix
	 * @return string
	 */
	protected function _uniqueAlias( $prefix = 'v' )
	{
		$count = count( $this->_parts[self::FROM] );
		return $prefix.$count;
	}

	/**
	 * Присоединяет к выборке таблицу дополнительных свойств объекта
	 *
	 * @param string $tableAlias
	 * @param array $field
	 */
	protected function _property_join( $tableAlias, $field )
	{
		$this->_join(
			self::LEFT_JOIN,
			array( $tableAlias => $field[Maestro_Mapper_Defines::F_TABLE] ),
			$tableAlias.'.OBID='.$this->_defines->tableAlias.'.OBID and '.$tableAlias.'.FIELDID='.$field['index'],
			array()
		);
	}

}
?>
