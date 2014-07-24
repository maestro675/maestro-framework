<?php

/**
 * Объект навигации по страницам
 *
 * @author maestro
 */
class Maestro_XmlObject_Paginator implements Maestro_XmlObject_Interface {

	/**
	 * Текстовые фрагменты
	 * 
	 * @var array
	 */
	public $str = array(
		'items' => '%d элементов',
		'page' => 'Страница %d',
		'full' => 'Полный список',
		'more' => 'далее',
		'total' => 'все'
	);
	
	/**
	 * Результирующий XML-документ
	 * 
	 * @var Maestro_Dom
	 */
	protected $_doc;
	
	/**
	 * Текущая страница
	 * 
	 * @var integer
	 */
	public $current = 1;
	
	/**
	 * Элементов на текущей странице
	 *  
	 * @var integer
	 */
	public $currentItemCount = 0;
	
	/**
	 * Элементов на страницу
	 * 
	 * @var integer
	 */
	public $countPerPage = 30;
	
	/**
	 * Всего страниц
	 * 
	 * @var integer
	 */
	public $countPages = 0;
	
	/**
	 * Всего элементов на всех страницах
	 * 
	 * @var integer
	 */
	public $totalItemCount = 0;
	
	/**
	 * Обработка клика на выбор конкретной страницы
	 * 
	 * @var string
	 */
	public $clickPage = 'alert(\'page: %d\');';
	
	/**
	 * Обработка клика на выбор всех страниц
	 * 
	 * @var string
	 */
	public $clickTotal = 'alert(\'all pages\');';

	/**
	 *
	 * @var string
	 */
	public $hrefPage = 'javascript:void(0);';

	/**
	 *
	 * @var string
	 */
	public $hrefTotal = 'javascript:void(0);';
	
	/**
	 * Опеределяет, как обрабатывать клики на элементы - как AJAX-запросы (по умолчанию)
	 * или как реферальные ссылки (href)
	 * 
	 * @var boolean
	 */
	public $clickIsAjax = true;
	
	/**
	 * разрешает кнопку "далее"
	 * 
	 * @var boolean
	 */
	public $clickNextAllowed = false;
	
	/**
	 * Показывать кнопку "все"
	 * 
	 * @var boolean
	 */
	public $allItemsAllowed = false;

	/**
	 * Устанавливает имя объекта.
	 * Используется для идентификации виджета в `view`.
	 *
	 * @param string $name
	 * @return Maestro_XmlObject_Helper
	 */
	public function setName($name) {
		$this->_name = Maestro_Common::filterName($name);
		return $this;
	}

	/**
	 * Возвращает имя объекта
	 *
	 * @return string
	 */
	public function getName() {
		return $this->_name;
	}

	/**
	 *
	 * @return Maestro_XmlObject_Helper
	 */
	public function getDocument() {

		if (is_object($this->_doc)) {
			return $this->_doc;
		}

		$this->_doc = new Maestro_Dom('1.0', 'UTF-8');
		$this->_doc->formatOutput = true;

		//
		$mainNode = $this->_doc->addNode($this->_doc, 'div');
		$mainNode->setAttribute('class', 'pagination');
		
		// иконка элемента
		$node = $this->_doc->addNode($mainNode, 'img');
		$node->setAttribute('src', '/img/icon_pages.gif');

		// элементов на текущей странице
		$node = $this->_doc->addNode($mainNode, 'span', sprintf($this->str['items'], $this->currentItemCount));
		$this->_doc->addNode($mainNode, 'span', ' • ');


		// номер текущей страницы
		$text = '';
		if ($this->current < 0) {
			$text = $this->str['full'];
		} else {
			$text = sprintf($this->str['page'], $this->current);
			if ($this->totalItemCount) {
				$text .= ' из ' . $this->countPages;
			}
		}
		$node = $this->_doc->addNode($mainNode, 'span', $text);
		$node->setAttribute('class', 'marked-red text-warning');

		$this->_doc->addNode($mainNode, 'span', ' • ');

		if ($this->current <= 0 && $this->currentItemCount) {
			$this->countPages = ceil($this->currentItemCount / $this->countPerPage);
		}


		// формируем список номеров страниц
		//if ($this->current > 0) {
		$spaces_pre = false;
		$spaces_post = false;
		for ($i = 1; $i <= $this->countPages; $i++) {
			if ($i == $this->current) {
				$node = $this->_doc->addNode($mainNode, 'strong', $i);
			} else {
				// -------
				if ($this->countPages >= 7) {
					// pre ...
					if ($i > 2 && $i < ($this->current - 1)) {
						if (!$spaces_pre) {
							$node = $this->_doc->addNode($mainNode, 'span', '. . .');
						}
						$spaces_pre = true;
						continue;
					}
					// post ...
					if ($i > ($this->current+1) && $i>1 && $i < ($this->countPages - 1)) {
						if (!$spaces_post) {
							$node = $this->_doc->addNode($mainNode, 'span', '. . .');
						}
						$spaces_post = true;
						continue;
					}
				}
				// -------
				$node = $this->_doc->addNode($mainNode, 'a', $i);
				if($this->clickIsAjax) {
					$node->setAttribute('href', sprintf($this->hrefPage, $i));
					$node->setAttribute('onclick', sprintf($this->clickPage, $i));
				} else {
					$node->setAttribute('href', sprintf($this->hrefPage, $i));
				}
			}
		}
		//}
		// если не задано общее количество элементов, то добавляем кнопку "далее"
		if (!$this->totalItemCount || $this->clickNextAllowed) {
			// при условии, что текущее количество элементов $currentItemCount не
			// меньше количества элементов на страницу $countPerPage
			if ($this->currentItemCount == $this->countPerPage) {
				$node = $this->_doc->addNode($mainNode, 'a', $this->str['more']);
				if($this->clickIsAjax) {
					$node->setAttribute('href', sprintf($this->hrefPage, $this->current + 1));
					$node->setAttribute('onclick', sprintf($this->clickPage, $this->current + 1));
				} else {
					$node->setAttribute('href', sprintf($this->hrefPage, $this->current + 1));
				}
			}
		}

		if ($this->allItemsAllowed) {
			$this->_doc->addNode($mainNode, 'span', ' • ');
			if ($this->current > 0) {
				//$node = $this->_doc->addNode($mainNode, 'span', '&#xa0;&#xa0;');
				$node = $this->_doc->addNode($mainNode, 'a', $this->str['total']);
				if($this->clickIsAjax) {
					$node->setAttribute('href', $this->hrefTotal);
					$node->setAttribute('onclick', $this->clickTotal);
				} else {
					$node->setAttribute('href', $this->hrefTotal);
				}
			} else {
				$node = $this->_doc->addNode($mainNode, 'strong', $this->str['total']);
			}
		}

		//$node = $this->_doc->addNode($mainNode, 'em', $this->countPerPage.' items per page');
		//$node->setAttribute('class', 'right');

		return $this->_doc;
	}
}

?>
