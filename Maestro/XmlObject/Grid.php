<?php
/**
 * Grid: построение таблицы по описанию столбцов и строк
 * с возможным расчетом формул по строками и столбцам
 *
 * @author Sergey Lysiansky, s.lysiansky@ucb-ua.com
 * @version    2.0
 */
class Maestro_XmlObject_Grid implements Maestro_XmlObject_Interface
{
	const NUMBER  = 0;
	const FLOAT   = 1;
	const PROCENT = 2;
	const TEXT    = 3;
	const DELIM   = 4;
	const HEADER  = 5;
	const NOMINAL = 6; // тож же FLOAT, только без подведения итогов
	const NOWRAP  = 7; // текст без переноса строк
	const DATE    = 8; // дата, заданная числом
	const DATESTR = 9; // дата в чужеродном формате (например, 01-JAN-2010)
	const HEADER2 = 10; // td c выравниванием по центру

	const REPLACE  = 'replace';
	const APPEND   = 'append';
	const IFNONE   = 'ifnone';
	const FROMCELL = 'fromcell';

	const F_THIS  = 'THIS';
	const F_CONST = 'CONST';
	const F_VAR   = 'VAR';
	const F_EVAL  = 'EVAL';
	const F_DATASRC  = 'DATASRC';
	const F_ABS   = 'ABS';
	const F_INNER = 'INNER';

	/**
	 * Формула проверки переполнение значения.
	 * Варианты:
	 * <pre>
	 * +OWERFLOW[X],[Y];
	 * +OWERFLOW[X];
	 * </pre>
	 * , где [X] максимально допустимое значение, при превышении которого
	 * подставляется значение [Y]. Если [Y] не указано, значение уменьшается до
	 * константы [X]
	 */
	const F_OVERFLOW = 'OVERFLOW';

	const RIGHT   = 'right';
	const LEFT    = 'left';
	const CENTER  = 'center';
	const TOP     = 'top';

	/**
	 * Доп.свойства ячеек td
	 */
	const CELL_CLASS      = 'class';
	const CELL_ROWSPAN    = 'rowspan';
	const CELL_COLSPAN    = 'colspan';
    const CELL_INPUT_SIZE = 'size';
    const CELL_INPUT_MAXL = 'maxlength';
	const CELL_ALIGN      = 'align';


	private static $_initCellData = array(
		self::CELL_CLASS   => false,
		self::CELL_ALIGN   => false,
		self::CELL_ROWSPAN => 0,
		self::CELL_COLSPAN => 0,
        self::CELL_INPUT_SIZE => 10,
        self::CELL_INPUT_MAXL => 0
	);

	private $step=0;

    protected static $digitalTypes = array( self::FLOAT, self::NOMINAL,  self::PROCENT );

	/**
	 * Имя пользовательской функции обработки нераспознанных конструкций в формулах.
	 * Функция должна быть определена следующий образом
	 * function ( $item, $row, $col, $grid ), где
	 *  - item - элемент формулы
	 *  - row  - индекс строки ячейки
	 *  - col  - индекс столбца ячейки
	 *  - grid - ссылка на текущий экземпляр данного класса Grid
	 *
	 * Функция должна возвращать вычисленное значение
	 *
	 * @var string|array
	 */
	public $_evalCallback = null;

	/**
	 * Служебный массив, в который заносятся расчитанные ячейки
	 * при запуске глобального расчета значений ячеек
	 *
	 * @var array
	 */
	private $_estimates;

	/**
	 * Служебный массив для определения подчинения строк.
	 * Используется для автоматической подстановки формул суммирования дочерних
	 * строк.
	 *
	 * @var array
	 */
	private $_relations;

	/**
	 * Параметры
	 *
	 * @var array
	 */
	protected $_attributes = array();

	/**
	 * Двухмерный массив ячеек [ROW][COL]
	 *
	 * @var array
	 */
	protected $_cells;    // значения ячеек
	protected $_rowspan;  // объединения строк
	protected $_colspan;  // объединения столбцов
	protected $_celldata; // доп.параметры ячеек

	/**
	 * Массив строк
	 *
	 * @var array
	 */
	protected $_rows;

	/**
	 * Массив столбцов
	 *
	 * @var array
	 */
	protected $_columns;

	/**
	 * Массив формул ячеек [ROW][COL]
	 *
	 * @var array
	 */
	protected $_formulas;

	/**
	 * Список промежуточных констант XX=>YY  (используется в формулах, пример +VARXX;)
	 *
	 * @var array
	 */
	protected $_vars;

	/**
	 * Внутренний источник данных. Массив из id строк и столбцов: [ROWID][COLID]=>[VALUE]
	 * Используется формулой F_INNER
	 *
	 * @var array
	 */
	protected $_innerData = array();

	/**
	 * DOM-документ таблицы
	 *
	 * @var Maestro_Dom
	 */
	protected $_doc;

	/**
	 * Конструктор класса
	 *
	 * @param array $options
	 */
	public function  __construct($options = null)
	{
		$this->setAttributes(array(
			'precision' => 2,
			'forceabs'  => false,
			'negatives' => true,
            'eyeplus'   => false,
			'debug'     => false,
            'class'     => 'tahoma',
            'id'        => false,
			'evaluated' => false,
			'header'    => true,
            'border'    => 0,
			'caption'   => false,
			'created'   => date('d.m.Y H:i'),
			'valign'    => '',
			'markfunc'  => 'mark',
			'export'    => false,
            'cellclick' => false
		));

		// опции
		if(is_array($options))
		{
			$this->addAttributes($options);
		}
	}

	/**
	 * Функция для переопределения в дочерних классах - серилизация
	 * таблицы, например, для сохранения в параметрах сесси и последующей выгрузки в Excel
	 */
	protected function export()
	{

	}

	/**
	 *
	 * @param array $data
	 */
	public function setInnerData($data)
	{
		$this->_innerData = $data;
	}

    /**
     *
     * @param array $data
     */
	public function mergeInnerData($data)
	{
		$this->_innerData = array_merge($this->_innerData, $data);
	}

	/**
	 * Добавление столбца
	 *
	 * @param string $caption Заголовок столбца
	 * @param integer $type Тип данных столбца
	 * @param integer|string $id Внешний идентификатор
	 * @param integer $width Ширина в пикселях (для вывода)
	 * @param integer $align Выравнивани по горизонтали (для вывода)
	 * @return integer Индекс добавленного столбца
	 */
	public function addCol( $title, $type=self::TEXT, $id=null, $width=null, $align=self::LEFT )
	{
		$n = count($this->_columns);
		$this->_columns[$n]['title']   = $title;
		$this->_columns[$n]['type']    = $type;
		$this->_columns[$n]['id']      = $id;
		$this->_columns[$n]['width']   = $width;
		$this->_columns[$n]['align']   = $align;
		$this->_columns[$n]['colspan'] = 1;
		$this->_columns[$n]['rowspan'] = 1;
		$this->_columns[$n]['input']   = false;
		$this->_columns[$n]['hide']    = false;
		$this->_columns[$n]['mark']    = false;
		$this->_columns[$n]['cell']    = false;
		return $n;
	}

	/**
	 * Устанавливает параметр для существующего столбца
	 *
	 * @param integer $colidx Индекс столбца
	 * @param string $name Имя существующего параметра
	 * @param mixed $value Новое значение
	 * @return void
	 */
	public function setCol( $colidx, $name, $value )
	{
		//if( isset( $this->_columns[$colidx][$name] ) )
		{
			$this->_columns[$colidx][$name] = $value;
		}
	}

	/**
	 * Возвращает параметры существующего столбца
	 *
	 * @param integer $colidx Индек стоблца
	 * @return array
	 */
	public function col( $colidx )
	{
		if( isset( $this->_columns[$colidx] ) )
		{
			return $this->_columns[$colidx];
		}
		else
			return false;
	}

	public function colCount()
	{
		return count($this->_columns);
	}

	public function rowCount()
	{
		return count($this->_rows);
	}

	/**
	 * Добавление строки
	 *
	 * @param string $caption Заголовок строки
	 * @param integer $type Тип данных строки
	 * @param integer|string $id Внешний идентификатор
	 * @param integer|string $id Индекс родительской строки или класс строки
	 * @return integer Индекс добавленной строкистолбца
	 */
	public function addRow( $title, $type=self::TEXT, $id=null, $owner=null )
	{
		$n = count($this->_rows);
		$this->_rows[$n]['title']   = $title;
		$this->_rows[$n]['type']    = $type;
		$this->_rows[$n]['id']      = $id;
		$this->_rows[$n]['owner']   = $owner;
		$this->_rows[$n]['input']   = false;
		$this->_rows[$n]['hide']    = false;
		$this->_rows[$n]['mark']    = false;
		$this->_rows[$n]['cell']    = false;
		$this->_rows[$n]['collapsed'] = false;
		//$this->_rows[$n]['childsum'] = false; // не вставлять функции суммирования нижестоящих строк
		return $n;
	}

	/**
	 * Устанавливает параметр для существующей строки
	 *
	 * @param integer $colidx Индекс строки
	 * @param string $name Имя существующего параметра
	 * @param mixed $value Новое значение
	 * @return void
	 */
	public function setRow( $rowidx, $name, $value )
	{
	//if( isset( $this->_rows[$rowidx][$name] ) )

		{
			$this->_rows[$rowidx][$name] = $value;
		}
	}

	/**
	 * Возвращает параметры существующей строки
	 *
	 * @param integer $colidx Индекс строки
	 * @return array
	 */
	public function row( $rowidx )
	{
		if( isset( $this->_rows[$rowidx] ) )
		{
			return $this->_rows[$rowidx];
		}
		else
			return false;
	}

	/**
	 * Устанавливает значение промежуточной переменной (константы)
	 *
	 * @param string $key Имя
	 * @param string|integer $value Значение
	 * @param string $mode Режим (замещения - `replace` или добавления - `append`)
	 */
	public function setVar( $key, $value, $mode=self::REPLACE )
	{
		if( $mode == self::REPLACE || !isset( $this->_vars[$key] ) )
		{
			$this->_vars[$key] = $value;
		}
		else
			if( $mode == self::APPEND )
			{
				$this->_vars[$key] += $value;
			}
	}

	/**
	 * Устанавливает формулу
	 *
	 * @param integer $rowidx Индекс строки
	 * @param integer $colidx Индекс столбца
	 * @param string $forumla Формула
	 * @param string $mode Режим (замещения - `replace` или добавления - `append`)
	 */
	public function setFormula( $rowidx, $colidx, $formula, $mode=self::REPLACE )
	{
		if( $mode == self::REPLACE )
		{
			$this->_formulas[$rowidx][$colidx] = $formula;
		}
		else
			if( $mode == self::APPEND )
			{
				if( isset( $this->_formulas[$rowidx][$colidx] ) )
				{
					$this->_formulas[$rowidx][$colidx] .= $formula;
				}
				else
				{
					$this->_formulas[$rowidx][$colidx] = '';
				}
			}
			else
				if( $mode == self::IFNONE )
				{
					if( !isset( $this->_formulas[$rowidx][$colidx] ) )
					{
						$this->_formulas[$rowidx][$colidx] = $formula;
					}
				}
	}


	/**
	 * Возвращает формулу
	 *
	 * @param integer $rowidx Индекс строки
	 * @param integer $colidx Индекс столбца
	 * @return string|boolena  Формула
	 */
	public function getFormula( $rowidx, $colidx )
	{
		if( isset( $this->_formulas[$rowidx][$colidx] ) )
		{
			return $this->_formulas[$rowidx][$colidx];
		}
		else
		{
			return false;
		}
	}

	/**
	 * Устанавливает значение ячейки
	 *
	 * @param integer $row Индекс строки
	 * @param integer $col Индекс столбца
	 * @param string $value Формула
	 * @param string $mode Режим (замещения - `replace` или добавления - `append`)
	 */
	public function setCell($row, $col, $value, $rowspan=0, $colspan=0, $mode=self::REPLACE )
	{
		if( $mode == self::REPLACE )
		{
			$this->_cells[$row][$col] = $value;
		}
		else
			if( $mode == self::APPEND)
			{
				if( isset( $this->_cells[$row][$col] ) )
				{
					$this->_cells[$row][$col] += $value;
				}
				else
				{
					$this->_cells[$row][$col] = 0;
				}
			}

		if( $rowspan )
			$this->_rowspan[$row][$col] = $rowspan;
		if( $colspan )
			$this->_colspan[$row][$col] = $colspan;
	}

	/**
	 * Возвращает значение ячейки
	 *
	 * @param integer $row
	 * @param integer $col
	 * @return mixed
	 */
	public function getCell($row, $col)
	{
		return isset($this->_cells[$row][$col]) ? $this->_cells[$row][$col] : false;
	}

	/**
	 * Устанавливает значение доп.параметра ячейки
	 *
	 * @param integer $row
	 * @param integer $col
	 * @param string $param
	 * @param string|integer $value
	 */
	public function setCellData($row, $col, $param, $value)
	{
		if(!array_key_exists($param, self::$_initCellData))
		{
			return;
		}

		$this->_celldata[$row][$col][$param] = $value;
	}

	/**
	 * Возвращает значение доп.параметра ячейки
	 *
	 * @param integer $row
	 * @param integer $col
	 * @param string $param
	 * @return mixed
	 */
	public function getCellData($row, $col, $param, $default=null)
	{
		if(isset($this->_celldata[$row][$col][$param]))
        {
            return $this->_celldata[$row][$col][$param];
        }
        else
        {
            return $default;
        }
	}

	/**
	 * Устанавливает отношение между "родительской" и "дочерней" строкой
	 *
	 * @param integer $row Индекс "родительской" строки
	 * @param integer $childrow Индекс "дочерней" строки
	 */
	public function setRelations( $ownerid, $childid )
	{
		$ownerrow = $this->findRow( 'id', $ownerid );
		$childrow = $this->findRow( 'id', $childid );

		$this->setRelationsRC( $ownerrow, $childrow );
	}

	public function setRelationsRC( $owner_row, $child_row )
	{
		if( $owner_row !== false && $child_row !== false)
		{
			$this->_relations[$owner_row][] = $child_row;
		}
	}

	/**
	 * Устанавливает объединение ячеек по столбцу
	 *
	 * @param integer $row Индекс строки
	 * @param integer $col Индекс столбца
	 * @param integer $span Количество объединяемых ячеек "вниз", начиная с текущей
	 */
	public function setColSpan($row, $col, $span)
	{
		$this->_colspan[$row][$col] = $span;
	}

	public function getColSpan($row, $col)
	{
		return isset($this->_colspan[$row][$col]) ? $this->_colspan[$row][$col] : 0;
	}

	public function getColProperty($col, $property)
	{
		return isset($this->_columns[$col][$property]) ? $this->_columns[$col][$property] : null;
	}

	public function getRowProperty($row, $property)
	{
		return isset($this->_rows[$row][$property]) ? $this->_rows[$row][$property] : null;
	}

	/**
	 * Устанавливает объединение ячеек по строке
	 *
	 * @param integer $row Индекс строки
	 * @param integer $col Индекс столбца
	 * @param integer $span Количество объединяемых ячеек "вправо", начиная с текущей
	 */
	public function setRowSpan($row, $col, $span)
	{
		$this->_rowspan[$row][$col] = $span;
	}

	public function getRowSpan($row, $col)
	{
		return isset($this->_rowspan[$row][$col]) ? $this->_rowspan[$row][$col] : 0;
	}

	/**
	 * Ищет первую строку с заданным атрибутом $attr, имеющим значение равное $value.
	 * Возвращает индекс найденной строки или логическое `false`
	 *
	 * @param string $attr Название параметра строки
	 * @param string $value Значение
	 * @return integer
	 */
	public function findRow( $attr, $value )
	{
		$findrowidx = false;
		foreach( $this->_rows as $rowidx => $rowdata )
			if( array_key_exists( $attr, $rowdata ) )
				if( $rowdata[$attr] == $value )
				{
					$findrowidx = $rowidx;
					break;
				}
		return $findrowidx;
	}

	/**
	 * Ищет первую колонку с заданным атрибутом $attr, имеющим значение равное $value.
	 * Возвращает индекс найденного стоблца или NULL
	 *
	 * @param string $attr Название параметра столбца
	 * @param string $value Значение
	 * @return integer
	 */
	public function findCol( $attr, $value )
	{
		$findcolidx = null;
		foreach($this->_columns as $colidx => $coldata)
        {
			if(array_key_exists($attr, $coldata) && $value == $coldata[$attr])
            {
                $findcolidx = $colidx;
                break;
            }
        }
		return $findcolidx;
	}

	/**
	 * Однопроходный метод расчета формул.
	 * Инфо о расчете каждой конкретной ячейки заносится в массив $this->estimates
	 *
	 * @return void
	 */
	public function evaluate()
	{
		if($this->getAttribute('evaluated')) {
			return;
		}
		
		$this->_estimates = array();
		
		$countRows = count( $this->_rows );
		$countCols = count( $this->_columns );

		for($r=0; $r < $countRows; $r++) {
			for($c=0; $c < $countCols; $c++) {
				if(isset($this->_formulas[$r][$c]))	{
					$this->_eval_cell($r, $c);
				}
			}
		}
		$this->setAttribute('evaluated', true);
	}

	/**
	 * Записывает параметры класса и структуру ячеек в указанный и
	 * существующий XML документ
	 *
	 * @param object $xml Объект класса XmlDocument
	 * @return void
	 */
	public function save(&$xml)
	{
		$ngrid = $xml->addNode( 'root', 'grid' );
		# список параметров
		$nlist = $xml->addNode( $ngrid, 'params');
		if( $this->_attributes )
			foreach( $this->_attributes as $key => $value )
			{
				$node = $xml->addNode( $nlist, $key, $value );
			}
		# столбцы
		$nlist = $xml->addNode( $ngrid, 'cols');
		if( $this->_columns )
			foreach( $this->_columns as $col => $data )
			{
				$node = $xml->addNode( $nlist, 'col', $data['title'], $col );
				foreach( $data as $key => $value )
					if( $key <> 'title' )
					{
						$node->setAttribute( $key, $value );
					//$xml->addNode( $node, $key, $value );
					}
			}
		# строки
		$nlist = $xml->addNode( $ngrid, 'rows');
		if( $this->_rows )
			foreach( $this->_rows as $row => $data )
			{
				$node = $xml->addNode( $nlist, 'row', $data['title'], $row );
				foreach( $data as $key => $value )
					if( $key <> 'title' )
					{
						$node->setAttribute( $key, $value );
					//$xml->addNode( $node, $key, $value );
					}
			}
		# данные
		$nlist = $xml->addNode( $ngrid, 'data');
		if( $this->_cells )
			foreach( $this->_rows as $row => $data )
			{
				$nrow = $xml->addNode( $nlist, 'row', null, $data['id']);
				foreach( $this->_columns as $col => $data )
				{
					$value = null;
					if( isset( $this->_cells[$row][$col] ) )
					{
						$value = $this->_cells[$row][$col];
					}
					$xml->addNode( $nrow, 'cell', $value );
				}
			}
	}

	/**
	 * Генерирует HTML-код таблицы данных
	 *
	 * @return string
	 */
	public function saveHTML()
	{
		$this->_create();
		return $this->_doc->saveXML();
	}

	/**
	 *
	 *
	 */
	protected function _create()
	{
		$this->_doc = new Maestro_Dom( '1.0', 'UTF-8' );
		$this->_doc->formatOutput = true;
		$node = $this->_doc->addNode( $this->_doc, 'table' );
		$node->setAttribute( 'border', $this->getAttribute('border') );
		$node->setAttribute( 'width', '100%' );
		$node->setAttribute( 'class', $this->getAttribute('class'));
		$node->setAttribute( 'cellpadding', 4 );
		$node->setAttribute( 'cellspacing', 0 );
        if(($id = $this->getAttribute('id')))
        {
            $node->setAttribute('id', $id);
        }

		# заголовок
		$ncols = $this->_doc->addNode( 'root', 'colgroup' );
		$nrow = false;

        // блок заголовка таблицы
		$cnode  = $this->_doc->addNode(Maestro_Dom::ROOT, 'caption');
		//$cnode = $this->_doc->addNode(Maestro_Dom::ROOT, 'caption', $this->getCaption());

		# выгрузка в Эксель
		if($this->getAttribute('export'))
		{
			//$xnode = $this->_doc->addNode(Maestro_Dom::ROOT, 'tr');
			$xnode = $this->_doc->addNode($cnode, 'span');
			$xnode->setAttribute('colspan', 100);
			//$xnode->setAttribute('align', 'right');
			$xnode->setAttribute('class', 'table-export');
			$n = $this->getName().'Export';
			$xnode->setAttribute('id', $n);

            // кнопка открытия на отдельной странице
			$node = $this->_doc->addNode($xnode, 'a', 'На отдельную страницу');//выгрузить в Excel&#xa0;');
			$node->setAttribute('href',    '/grid');
			$node->setAttribute('title',   'Показать на отдельной странице');
			$node->setAttribute('target',  '_blank');
			//$this->_doc->addNode($xnode, 'img')->setAttribute('src', '/icon/22/text-xhtml+xml.png');

            // кнопка открытия на отдельной странице
			/*$xnode = $this->_doc->addNode($xnode, 'a', '');
			$xnode->setAttribute('href',    '/csv');
			$xnode->setAttribute('title',   'Выгрузить в текстовый файл c разделителями');
			$xnode->setAttribute('target',  '_blank');
			$this->_doc->addNode($xnode, 'img')->setAttribute('src', '/icon/22/text-plain.png');*/
			
			$node = $this->_doc->addNode($xnode, 'span', '&#xa0;|&#xa0;');
			$node->setAttribute('class', 'muted');

            // кнопка выгрузки в Эксель
			$node = $this->_doc->addNode($xnode, 'a', 'Выгрузить в MS Excel');//выгрузить в Excel&#xa0;');
			$node->setAttribute('href',  'javascript:void(0);');
			//$node->setAttribute('title', 'Выгрузить в MS Excel');
			//$xnode->setAttribute('class', 'marked-green smalltext');
			$node->setAttribute('onclick', "Suite.query('/export', { target: '{$n}', indicatortype: 'short'});");
			//$this->_doc->addNode($node, 'img')->setAttribute('src', '/icon/22/application-vnd.ms-excel.png');
		}

		# название таблицы
        $caption = $this->getCaption();
        if(!empty($caption))
        {
            $this->_doc->addNode($cnode, 'h5', $caption )->setAttribute('class', 'marked-brown');
            $captionsub = $this->getAttribute('captionSub');
            if($captionsub)
            {
                $this->_doc->addNode($cnode, 'div', $captionsub)->setAttribute('class', 'sub');
            }
        }


		# если заголовок отображается
		if( $this->getHeader() )
		{
			$nrow  = $this->_doc->addNode( 'root', 'tr' );
		}
		//$nrow->setAttribute( 'class', 'dark' );

		if( $this->_columns )
		{
			foreach( $this->_columns as $col => $data )
			{
				if( !$data['hide'] )
				{
					$node = $this->_doc->addNode( $ncols, 'col' );
					$node->setAttribute( 'width', $data['width'] );
					if( $nrow )
					{
						$node = $this->_doc->addNode( $nrow, 'th', $data['title'] );
					}
				}
			}
		}
		# данные
		$nbody = $this->_doc->addNode( 'root', 'tbody');
		if( $this->_cells )
		{
			$rspan = 0;
			$cspan = 0;
			foreach( $this->_rows as $row => $rdata )
			{
				if( $rdata['hide'] ) continue;

				$nrow = $this->_doc->addNode( $nbody, 'tr', null, $rdata['id'] );
				$nrow->setAttribute( 'class', $rdata['owner'] );
				$nrow->setAttribute( 'title', $rdata['title'] );
				$nrow->setAttribute( 'valign', $this->getAttribute('valign') );
                // display: none
                if( $rdata['collapsed'] )
                {
                    $nrow->setAttribute( 'style', 'display: none;' );
                }

				foreach( $this->_columns as $col => $cdata )
				{
					if($cspan)
					{
						$cspan--;
						continue;
					}

					if( $cdata['hide'] ) continue;

                    $cell_formula = $this->getFormula( $row, $col );

					//if( !isset( $this->_cells[$row][$col] ) ) continue;

					if( !array_key_exists($row, $this->_cells) ) continue;
					if( !array_key_exists($col, $this->_cells[$row]) ) continue;

					$cell_tag = ( self::HEADER == $rdata['type'] ) ? 'th' : 'td';

					# в режиме отладки выводим свойства ячейки
					if($this->getAttribute('debug'))
					{
						$ncell = $this->_doc->addNode( $nrow, $cell_tag );
						if( $col == 0 )
						{
							$nsubcell = $this->_doc->addNode( $ncell, 'b', " ID:".$rdata['id']."" );
							$nsubcell->setAttribute( 'class', 'marked' );
						}
						else
						{
							$this->_doc->addNode( $ncell, 'span', "R{$row}C{$col}" );
							if( $cell_formula && !empty($cell_formula) )
							{
								$nsubcell = $this->_doc->addNode( $ncell, 'img' );
								$nsubcell->setAttribute( 'src', '/icon/16/info.png' );
								$nsubcell->setAttribute( 'title', $cell_formula );
							}

						}
					}
					# в режиме редактирования выводим поле ввода
					elseif( $rdata['input'] && $cdata['input'] )
					{
						$ncell = $this->_doc->addNode( $nrow, $cell_tag );
                        $ncell->setAttribute('class', 'input');
						$value = $this->getCell( $row, $col );
						$value = $this->_format( $rdata['type'], $cdata['type'], $value, 'input' );

						$ninput = $this->_doc->addNode( $this->_doc->addNode($ncell, 'div'), 'input' );
						$size = $this->getCellData($row, $col, self::CELL_INPUT_SIZE, 10);
						if( $cdata['type'] == self::PROCENT )
						{
							$size = 5;
						}
						$ninput->setAttribute('type', 'text');
						$ninput->setAttribute('size', $size);
						$ninput->setAttribute('name', "cells[{$rdata['id']}][{$cdata['id']}]");
						$ninput->setAttribute('value', $value);
                        if(($maxl = $this->getCellData($row, $col, self::CELL_INPUT_MAXL)))
                        {
    						$ninput->setAttribute(self::CELL_INPUT_MAXL, $maxl);
                        }
					}
					// обычный вывод
					else
					{
						$value = $this->getCell( $row, $col );
						$origin_value = $value;

						//format value
						$value = $this->_format( $rdata['type'], $cdata['type'], $value);

						// отметка в ячейке
						if( $rdata['mark'] && $cdata['mark'] )
						{
							$ncell = $this->_doc->addNode( $nrow, $cell_tag );
                            $ncell->setAttribute('class', 'marked');
                            $ncell->setAttribute('onclick', 'gridCell(this);');

							$markNode = $this->_doc->addNode($ncell, 'a', $value);
							$markNode->setAttribute('href', 'javascript:void(0);');
							$markNode->setAttribute('onclick', sprintf("%s('%s', '%s');", $this->getAttribute('markfunc'), $rdata['id'], $cdata['id']) );
							$markNode->setAttribute('class', 'mark');
						}
                        else
						// отметка в ячейке
						if( $rdata['cell'] && $cdata['cell'] )
						{
							$ncell = $this->_doc->addNode( $nrow, $cell_tag, $value );
                            //if(!$cell_formula || $cell_formula == '+INNER;' || $cell_formula == '+INNER')
                            {
                                $ncell->setAttribute('class', 'cell');
                                $ncell->setAttribute('onclick', 'gridCell(this); '.sprintf("%s('%s', '%s');", $this->getAttribute('markfunc'), $rdata['id'], $cdata['id']));
                            }
						}
						else
						{
							$ncell = $this->_doc->addNode( $nrow, $cell_tag, $value );
                            if($this->getAttribute('cellclick'))
                            {
                                $ncell->setAttribute('onclick', 'gridCell(this);');
                            }
						}

						// если разрешено, раскрасим отрицательные числа
						if( $this->getAttribute('negatives') && in_array( $cdata['type'], self::$digitalTypes ) )
						{
							if( $origin_value < 0)
							{
								$ncell->setAttribute( 'class', 'negative' );
							}
						}
					}

					// align
					if( !empty($cdata['align']) && !is_null($cdata['align']) && self::HEADER != $rdata['type'] )
					{
						$ncell->setAttribute( 'align', (self::HEADER2 == $rdata['type']) ? self::CENTER : $cdata['align'] );
					}
					//rowspan
					if( isset( $this->_rowspan[$row][$col] ) )
					{
						$rspan = $this->_rowspan[$row][$col];
						$ncell->setAttribute( 'rowspan', $rspan );
					}
					//colspan
					if( isset( $this->_colspan[$row][$col] ) )
					{
						$cspan = $this->_colspan[$row][$col];
						$ncell->setAttribute( 'colspan', $cspan );
					}
					// nowrap
					if( in_array( $cdata['type'], array( self::NUMBER, self::FLOAT, self::PROCENT, self::NOMINAL, self::NOWRAP ) ) )
					{
						if( self::HEADER <> $rdata['type'] )
						{
							$ncell->setAttribute( 'nowrap', 'nowrap' );
						}
					}

					// доп.параметры ячейки
    				// td->class
                    $_tdclass = $this->getCellData($row, $col, self::CELL_CLASS);
                    if($_tdclass)
                    {
                        $ncell->setAttribute(self::CELL_CLASS, $_tdclass);
                    }
                    $_tdalign = $this->getCellData($row, $col, self::CELL_ALIGN);
                    if($_tdalign)
                    {
                        $ncell->setAttribute(self::CELL_ALIGN, $_tdalign);
                    }
					/*if(isset($this->_celldata[$row][$col]))
					{
						$celldata = $this->_celldata[$row][$col];
						// td->class
						if(isset($celldata[self::CELL_CLASS]))
						{
							$ncell->setAttribute(self::CELL_CLASS, $celldata[self::CELL_CLASS]);
						}
					}*/

					if($cspan) $cspan--;
				}
			}
		}

		// export
		if(false !== $this->getAttribute('export'))
		{
			$this->export();
		}
	}

	/**
	 * Прописывает формулы суммирования "дочерних" строк
	 *
	 */
	public function prepare( $mode=self::REPLACE )
	{
		if( $this->_rows )
		foreach( $this->_rows as $row => $rdata )
		{
			$frml = '';
			# составляем формулу суммирования по всем дочерним строкам
			if( isset( $this->_relations[$row] ) )
				foreach( $this->_relations[$row] as $rr => $childrow )
				{
					$frml .= "+R{$childrow};";
				}
			# в каждую "числовую" ячейку строки прописываем данную формулу
			foreach( $this->_columns as $col => $cdata )
			{
				if( !empty( $frml ) )
				{
					if( $cdata['type'] == self::NUMBER || $cdata['type'] == self::FLOAT )
					{
						$this->setFormula( $row, $col, $frml, $mode );
					}
					else
					if(!$this->getCell($row, $col))
					{
						$this->setCell( $row, $col, '&#xa0;' );
					}
				}
			}
		}
	}

	/**
	 * Форматирование содержимого ячейки по типу столбца
	 *
	 * @param mixed $rtype Тип строки
	 * @param integer $ctype Тип столбца
	 * @param mixed $value Значение ячейки
	 * @param mixed $intype Признак строки редактирования в ячейке
	 *
	 * @return numeric Расчитанное значение ячейки
	 */
	protected function _format( $rtype, $ctype, $value, $intype = '' )
	{
		$out = trim( $value );

		if( $this->getAttribute('debug') || self::HEADER == $rtype  || self::NOMINAL == $rtype)
		{
			return $out;
		}

		$delim    = ( 'input' == $intype ) ? '' : ' ';
		$decdelim = ( 'input' == $intype ) ? '.' : ',';
		$prec     = $this->getAttribute('precision', 0);
        $eyeplus  = '';

		if( $ctype == self::FLOAT || $ctype == self::PROCENT  )
		{
            // значения в пределах 1 не округляем никогда
            if(abs($value) < 1)
            {
                $prec = 2;
            }

            if(is_numeric($value))
            {
                if($ctype == self::FLOAT && $this->getAttribute('eyeplus'))
                {
                    $eyeplus = $value > 0 ? '+' : '';
                }
                $out = $eyeplus.number_format( $value, $prec, $decdelim, $delim );
            }
            else
                $out = $value;
			//$out = str_replace( '*', '&#xa0;' , $out );
		}
		elseif( $ctype == self::DATE  )
		{
			$out = ($value && '&#xa0;' <> $value) ? date('d.m.Y', $value) : '&#xa0;';
			//$out = str_replace( '*', '&#xa0;' , $out );
		}
		elseif( $ctype == self::DATESTR  )
		{
            if(!ctype_digit($value))
            {
                $value = strtotime($value);
            }
			$out = ($value && '&#xa0;' <> $value) ? date('d.m.Y', $value) : '&#xa0;';
			//$out = str_replace( '*', '&#xa0;' , $out );
		}
		elseif( $ctype == self::NOMINAL || $ctype == self::NUMBER )
		{
            if(is_numeric($value)) $out = number_format( $value, 0, $decdelim, $delim );
            else
            {
                $out = $value;
                //echo 'ожидалось число ['.$value.']<br/>';
            }
		}

		if( empty( $value ) || !$value )
		{
			if( 'input' == $intype )
			{
				$out = '';
			}
			else
			{
				$out = '&#xa0;';
			}
		}

		return $out;
	}



	/**
	 * Расчет формулы одной ячейки. Рекурсивная функция
	 *
	 * @param integer $rowidx Индекс строки
	 * @param integer $colidx Индекс столбца
	 *
	 * @return numeric Расчитанное значение ячейки
	 */
	private function _eval_cell( $rowidx, $colidx, $formula=self::FROMCELL )
	{
		$out_value = 0;

		if( !isset( $this->_cells[$rowidx][$colidx] ) )
		{
			$this->_cells[$rowidx][$colidx] = 0;
		}

		$start_value = $this->_cells[$rowidx][$colidx];

		if( $formula == self::FROMCELL )
		{
			if( isset( $this->_formulas[$rowidx][$colidx] ) )
			{
				if( isset( $this->_estimates[$rowidx][$colidx] ) && $this->_estimates[$rowidx][$colidx] == true )
				{
					return $start_value;
				}
				else
				{
					$formula = $this->_formulas[$rowidx][$colidx];
				}
			}
			else
			{
				return $start_value;
			}
		}

		$evalue = $start_value;

		$items = explode( ";", $formula );
		foreach( $items as $i => $item )
		{
			if( empty( $item ) ) continue;

			$operation = substr( $item, 0, 1 );
			$evalue = '';

			$sign = (int)( ( substr( $item, 0, 1 ) == '-' ) ? -1 : 1 );
			$what = substr( $item, 1, 1 );

			# получить значения ячейки RxCy
			if ( preg_match( "/^(.)R(.+)C(.+)/", $item, $matches ) )
			{
				$operation = $matches[1];
				$rx        = $matches[2];
				$cx        = $matches[3];
				$evalue = $this->_eval_cell( $rx, $cx );
			}
			# значение ячейки Cx в текущей строке
			elseif ( preg_match( "/^(.)C([[:digit:]]+)/", $item, $matches ) )
			{
				$operation = $matches[1];
				$rx        = $rowidx;
				$cx        = $matches[2];
				$evalue = $this->_eval_cell( $rx, $cx );
			}
			# значение ячейки Rx в текущем столбце
			elseif ( preg_match( "/^(.)R([[:digit:]]+)/", $item, $matches ) )
			{
				$operation = $matches[1];
				$rx        = $matches[2];
				$cx        = $colidx;
				$evalue = $this->_eval_cell( $rx, $cx );
			}
			# значение ячейки в строке с id=x в текущем столбце (RIDx)
			elseif ( preg_match( "/^(.)RID([[:digit:]]+)/", $item, $matches ) )
			{
				$operation = $matches[1];
				$rx        = $this->findRow( 'id', $matches[2] );
				$cx        = $colidx;
				if( $rx === false ) $rx = -1;

				$evalue = $this->_eval_cell( $rx, $cx );
			}
			# значение ячейки в столбце с id=x в текущей строке (CIDx)
			elseif ( preg_match( "/^(.)C#(.+)/", $item, $matches ) )
			{
				$operation = $matches[1];
				$col_index = $matches[2];

				$rx        = $rowidx;
				$cx        = $this->findCol('id', $col_index);
				$evalue    = $this->_eval_cell( $rx, $cx );
				/*if(is_null($cx)) {
					echo 'is null '.$item.'<br/>';
				}*/
				//echo $item.'='.$cx.'['.$evalue.'] cid:('.$col_index.') <br/>';
			}
			# значение ячейки в строке с id=x в текущем столбце (RIDx)
			elseif ( preg_match( "/^(.)ROW_(.+)/", $item, $matches ) )
			{
				$operation = $matches[1];
				$rx        = $this->fndRow( 'id', $matches[2] );
				$cx        = $colidx;
				if( $rx === false ) $rx = -1;

				$evalue = $this->_eval_cell( $rx, $cx );
			}
			# текущее значение
			elseif ( substr( $item, 1 ) == self::F_THIS )
			{
				$evalue = $start_value;
			}
			# модуль числа
			elseif ( substr( $item, 1 ) == self::F_ABS )
			{
				$evalue = abs($out_value);
			}
			# проверка превышения лимита, сокращенный вариант
			elseif ( preg_match( '/^(.)'.self::F_OVERFLOW.'([[:digit:]]+)$/', $item, $matches ) )
			{
				if($out_value > $matches[2])
				{
					$out_value = 0;
					$evalue    = $matches[2];
				}
			}
			# проверка превышения лимита
			elseif ( preg_match( '/^(.)'.self::F_OVERFLOW.'([[:digit:]]+),([[:digit:]]+)$/', $item, $matches ) )
			{
				if($out_value > $matches[2])
				{
					$out_value = 0;
					$evalue    = $matches[3];
				}
			}
			# просто константа
			elseif ( preg_match( "/^(.)".self::F_CONST."(.+)/", $item, $matches ) )
			{
				$operation = $matches[1];
				$evalue = floatval( $matches[2] );
			}
			# случайное число из указанного диапазона
			elseif ( preg_match( "/^(.)RAND([[:digit:]]+)/", $item, $matches ) )
			{
				$operation = $matches[1];
				$sup = floatval( $matches[2] );
				$evalue = rand( 0, $sup );
			}
			# внешняя переменная
			elseif ( preg_match( "/^(.)".self::F_VAR."(.+)/", $item, $matches ) )
			{
				$operation = $matches[1];
				$v = $matches[2];
				if( array_key_exists( $v, $this->_vars ) )
				{
					$evalue = $this->_vars[$v];
				}
			}
			# вычислить значение
			elseif ( preg_match( "/^(.)".self::F_EVAL."(.+)/", $item, $matches ) )
			{
				$operation = $matches[1];
				pre( "\$evalue=" . $matches[2]);
				try
				{
					$evalue = 0;
					eval( "\$evalue=" . $matches[2] . ";" );
				}
				catch ( Exception $e )
				{
					pre( $e->getMessage() );
				}
			}
			# внешний источник данных - двумерный массив с индексами, совпадающими с
			# атрибутами `id` столбцов и строк
			elseif ( preg_match( "/^(.)".self::F_DATASRC."(.+)/", $item, $matches ) )
			{
				$operation = $matches[1];
				$source = $matches[2];
				$rid = $this->_rows[$rowidx]['id'];
				$cid = $this->_columns[$colidx]['id'];

				$data = Zend_Registry::isRegistered($source) ? Zend_Registry::get($source) : array();
				if( $data && isset( $data[$rid][$cid] ) )
				{
					$evalue = $data[$rid][$cid];
				}
				else
				{
					$evalue = 0;
				}
			}
			# внутренний источник данных
			//elseif ( preg_match( "/^(.)".self::F_INNER."/", $item, $matches ) )
			elseif ( substr( $item, 1 ) == self::F_INNER )
			{
				$rid = $this->_rows[$rowidx]['id'];
				$cid = $this->_columns[$colidx]['id'];

				if( isset($this->_innerData[$rid][$cid]))
				{
					$evalue = $this->_innerData[$rid][$cid];
				}
				else
				{
					$evalue = 0;
				}
			}
			# определенная пользователем функция обработки остальных типов
			elseif($this->_evalCallback)
			{
				$evalue = call_user_func( $this->_evalCallback, $item, $rowidx, $colidx, $this );
			}

			// применяем полученное на этом фрагменте значение к значению ячейки
			switch( $operation )
			{
				case '+':
					$out_value += $evalue;
					break;
				case '-':
					$out_value -= $evalue;
					break;
				case '*':
					$out_value *= $evalue;
					break;
				case '/':
					if((int)$evalue)
					{
						$out_value = $out_value/$evalue;
					}
					break;
				case '=':
					$out_value = $evalue;
					break;
				default:
					pre(sprintf("unknown operation `%s` in R%sC%s", $operation, $rowidx, $colidx));
					break;
			}
		}

		$this->_cells[$rowidx][$colidx] = $out_value;
		$this->_estimates[$rowidx][$colidx] = true;
		return $out_value;
	}






	/**
	 * `Magic` метод, устанавливающий значение параметра
	 *
	 * @param string $name Имя параметра
	 * @param string $value Значение
	 * @return void
	 */
	/*public function __set($name, $value)
	{
		if( array_key_exists( $name, $this->_attributes ) )
		{
			$this->_attributes[$name] = $value;
		}
	}*/

	/**
	 * `Magic` метод получения значение параметра
	 *
	 * @param string $name Имя параметра
	 * @return mixed
	 */
	/*public function __get($name)
	{
		if( isset( $this->_attributes[$name] ) )
		{
			return $this->_attributes[$name];
		}
		else
			return null;
	}*/

	/**
	 *
	 * @param integer $level
	 */
	public function getClassByLevel( $level )
	{
		switch( $level )
		{
			case 0:
				$class = 'levelall';
				break;
			case 1:
				$class = 'level0';
				break;
			case 2:
				$class = 'level1';
				break;
			case 3:
				$class = 'level2';
				break;
			case 4:
				$class = 'level3';
				break;
			default:
				$class = '';
				break;
		}
		return $class;
	}

	/**
	 * Сохраняет данные класса в массив
	 *
	 * @return array
	 */
	public function serialize()
	{
		$store['attributes'] = $this->_attributes;
		$store['rows']       = $this->_rows;
		$store['columns']    = $this->_columns;
		$store['rowspan']    = $this->_rowspan;
		$store['colspan']    = $this->_colspan;
		$store['cells']      = $this->_cells;
		return $store;
	}

	/**
	 * Заполняет данные класса из массива
	 *
	 * @param array $store
	 */
	public function unserialize( $store )
	{
		$this->_attributes = $store['attributes'];
		$this->_rows       = $store['rows'];
		$this->_columns    = $store['columns'];
		$this->_rowspan    = $store['rowspan'];
		$this->_colspan    = $store['colspan'];
		$this->_cells      = $store['cells'];
	}

	/**
	 *
	 * @return Maestro_Dom
	 */
	public function getDocument()
	{
		$this->_create();
		return $this->_doc;
	}

	/**
	 * Устанавливает атрибут формы
	 *
	 * @param  string $key
	 * @param  mixed $value
	 * @return Maestro_Form
	 */
	public function setAttribute($key, $value)
	{
		$key = (string) $key;
		$this->_attributes[$key] = $value;
		return $this;
	}

	/**
	 * Добавляем массив атрибутов формы
	 *
	 * @param array $attributes
	 * @return Maestro_Form
	 */
	public function addAttributes(array $attributes)
	{
		foreach($attributes as $key => $value)
		{
			$this->setAttribute($key, $value);
		}
		return $this;
	}

	/**
	 * Устанавливает массив атрибутов формы.
	 * Предыдущие данные удаляются.
	 *
	 * @param array $attributes
	 * @return Maestro_Form
	 */
	public function setAttributes(array $attributes)
	{
		$this->clearAttributes();
		return $this->addAttributes($attributes);
	}

	/**
	 * Возвращает значение атрибута
	 *
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function getAttribute($key, $default = null)
	{
		$key = (string) $key;
		if(!isset($this->_attributes[$key]))
		{
			return $default;
		}
		return $this->_attributes[$key];
	}

	/**
	 * Возвращает все атрибуты и параметры
	 *
	 * return array
	 */
	public function getAttributes()
	{
		return $this->_attributes;
	}

	/**
	 * Удаляет атрибут
	 *
	 * @param string $key
	 * @return bool
	 */
	public function removeAttribute($key)
	{
		if(isset($this->_attributes[$key]))
		{
			unset($this->_attributes[$key]);
			return true;
		}
		return false;

	}

	/**
	 * Удааляет все атрибуты формы
	 *
	 * @return Maestro_Form
	 */
	public function clearAttributes()
	{
		$this->_attributes = array();
		return $this;
	}

	/**
	 * Устанавливает имя таблицы.
	 * Используется для идентификации виджета в `view`.
	 *
	 * @param string $name
	 * @return Maestro_XmlObject_Grid
	 */
	public function setName($name)
	{
		$name = Maestro_Common::filterName($name);
		if('' == (string)$name)
		{
			throw new Maestro_Form_Exception('Некорректное имя таблицы. Должно быть непустым и содержать только корректные символы');
		}

		$this->setAttribute('name', $name);
		return $this;
	}

	/**
	 * Возвращает имя формы
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->getAttribute('name');
	}

	/**
	 *
	 * @param string $str
	 * @return this
	 */
	public function setCaption($str)
	{
		$this->setAttribute('caption', $str);
		return $this;
	}

	/**
	 *
	 * @param string $str
	 * @return this
	 */
	public function setCaptionSub($str)
	{
		$this->setAttribute('captionSub', $str);
		return $this;
	}

	/**
	 *
	 * @return string
	 */
	public function getCaption()
	{
		return $this->getAttribute('caption');
	}

	/**
	 *
	 * @param bool $show
	 * @return this
	 */
	public function setHeader($show)
	{
		$this->setAttribute('header', $show);
		return $this;
	}

	/**
	 *
	 * @return bool
	 */
	public function getHeader()
	{
		return $this->getAttribute('header');
	}
}

