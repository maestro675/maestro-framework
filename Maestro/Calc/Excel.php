<?php

/**
 * Description of Excel
 *
 * @author maestro
 */
class Maestro_Calc_Excel extends Maestro_Calc_Sheet 
{
	/**
	 *
	 * @var PHPExcel 
	 */
	protected $xl;
	
	/**
	 *
	 * @var Maestro_Calc_Sheet 
	 */
	protected $grid;
	
	const STYLE_HEADER_COLOR = 'FFE1E1E1';
	const STYLE_HEADER_BG    = 'FF555555';
	
	/**
	 * 
	 * @return type
	 */
	public function getDocument($calculate = false) {
		if($calculate) {
			$this->_prepareCells();
			$this->estimateStart();
		}
		//
		$this->xl = new PHPExcel();		
		$this->_createExcelDocument();
		return $this->xl;
	}
	
	protected function prepareText($str) {
		return strip_tags(str_replace(
				array("<br>", "<br/>", '<hr>', "&nbsp;", "&#xa0;", "&#8226;"),
				array("\n", "\n", "\n", "", "", ""), 
				$str
		));
	}
	
	/**
	 * 
	 */
	protected function _createExcelDocument() {
		$this->xl->getProperties()->setCreator("site.ucb-ua.com")
				->setLastModifiedBy("site.ucb-ua.com")
				->setTitle($this->getProperty('caption'))
				->setSubject($this->getProperty('caption1'));
		$this->xl->setActiveSheetIndex(0);
		$this->xl->getActiveSheet()->getDefaultStyle()->getFont()->setName('Arial Cyr');

		// 
		$columns  = $this->getColKeys();		
		$rows     = $this->getRowKeys();
		$rowIndex = 1;
		$sheet    = $this->xl->getActiveSheet();
		
		// caption
		$sheet->setCellValue('A1', $this->prepareText($this->getProperty('caption')));
		$sheet->getStyle('A1')->getFont()->setSize(13);
		$sheet->getStyle('A1')->getFont()->setBold(true);

		// subcaption
		if (($subcaption = $this->getProperty('caption1'))) {
			$rowIndex++;
			$sheet->getDefaultStyle()->getFont()->setName('Arial Cyr');
			$sheet->setCellValue('A2', $this->prepareText($subcaption));
			$sheet->getStyle('A2')->getFont()->setSize(9);
			$sheet->getStyle('A2')->getFont()->setBold(true);
		}
		
		// column width		
		//$colIndex   = 0;
		foreach($columns as $column) {
			if(!$this->getColProperty($column, 'show')) {
				continue;
			}
			//
			$width = $this->getColProperty($column, 'width');
			$type  = $this->getColProperty($column, 'type');
			if (true === strpos($width, '%')) {
				$width = 50;
			} elseif (empty($width)) {
				if('percent' == $type) {
					$width = 11;
				} else
				if(array_key_exists($type, self::$_types['numeric'])) {
					$width = 14;
				} else {
					$width = 9;
				}
			}
			$sheet->getColumnDimension($this->getColProperty($column, 'AAA'))->setWidth($width);
		}
		
		// header
		if($this->getProperty('header')) {
			$rowIndex++;
			foreach($columns as $column) {
				$acol  = $this->getColProperty($column, 'AAA') . $rowIndex;
				$sheet->setCellValue($acol, $this->prepareText($this->getColProperty($column, 'name')));
			}
		}
		
		// data
		foreach($rows as $row) {
			if(!$this->getRowProperty($row, 'show')) {
				continue;
			}
			$rowIndex++;
			$ci = $cskip = 0;
			foreach($columns as $col) {
				if(!$this->getColProperty($column, 'show')) {
					continue;
				}
			}
		}

	}
}
