<?php

/**
 * BaseView: базовый абстрактный класс вывода
 *
 * @author Sergey Lysiansky, s.lysiansky@ucb-ua.com
 * @version    1.0
 */
abstract class Maestro_View_Base {

	/**
	 * параметр, определяющий, нужно ли подключать файл xsl-шаблона,
	 * соответствующий действию
	 *
	 * @var boolean
	 */
	protected $including = true;

	/**
	 * Массив дополнительных параметров для xslt-процессора
	 *
	 * @var array
	 */
	protected $params;

	/**
	 *
	 * @var Maestro_Controller_Base
	 */
	protected $controller;

	/**
	 * результируюущий документ, полученный в результате xslt-преобразований
	 *
	 * @var DomDocument
	 */
	protected $document;

	/**
	 * Именованный массив отдельных документов, вставляемых в результирующий
	 * документ вместо тэгов <mf:object name='key'>, где `key` - существующий
	 * ключ данного массива
	 *
	 * @var array of DomDocument
	 */
	protected $_imported_objects = array();
	protected $_include_files = array();
	protected $processor;
	protected $stylesheet;
	protected $echoNode;
	
	/**
	 * Путь к файлу шаблона для текущего действия контроллера. Если не задано с помощью 
	 * публичного метода setActionFile(), то вычисляется из имён контроллера и действия
	 * 
	 * @var string 
	 */
	protected $_action_file;

	/**
	 * Список файлов javascript, включаемых в конечный рендер
	 * 
	 * @var array
	 */
	protected $_include_scripts = array();

	/**
	 * Режим, установленный фабрикой view
	 *
	 * @var string
	 */
	protected $_mode;

	/**
	 *
	 */
	public $withErrors = false;

	/**
	 * Признак разрешения выполнения xslt-преобразований документа
	 *
	 * @var bool
	 */
	protected $_transformationEnable = true;
	
	/**
	 *
	 * @var string
	 */
	protected $widget_content;

	/**
	 * Конструктор. Передаем в качестве параметра ссылку на контроллер.
	 *
	 * @param Maestro_Controller_Base $owner_controller
	 * @param string $mode
	 */
	public function __construct(Maestro_Controller_Base $owner_controller, $mode) {
		$this->_mode = $mode;

		$this->processor = new XSLTProcessor();

		$this->stylesheet = new Maestro_Dom();
		$this->stylesheet->formatOutput = true;
		$this->stylesheet->preserveWhiteSpace = true;

		$this->controller = $owner_controller;

		$this->params = array();
		$this->params['_charset'] = Maestro_App::getConfig()->System->charset;
		$this->params['_version'] = Maestro_App::getConfig()->System->version;
	}

	/**
	 * Используется контроллером для формирования вывода.
	 *
	 */
	public function displayResult() {
		// доп.обработка шаблона
		$this->prepare();

		// Массив $params добавляем в процессор в качестве параметров.
		foreach ($this->params as $key => $value) {
			if (!is_array($value) && !is_object($value)) {
				$this->processor->setParameter('', $key, $value);
			}
		}

		if ($this->_transformationEnable) {
			// загружаем основой файл xslt
			$filename = $this->wrapperfile();
			if(!is_readable($filename)) {
				throw new Maestro_Exception('Wrapper is not exists or readable: '.$filename);
			}
			
			libxml_use_internal_errors(true);
			try {
				// если документ был загружен
				if($this->stylesheet->load($filename)) {
					if ($this->including && !$this->withErrors) {
						// включаем шаблон слоя
						$this->addIncludeFile($this->basefile());
						// включаем шаблон действия в основой шаблон
						$this->addIncludeFile($this->getActionFile());
						foreach ($this->_include_files as $filename) {
							//if(!is_readable($filename)) {
							//	throw new Maestro_Exception('Include file is not exists or readable: '.$filename);
							//}
							if($filename) {
								$ns = $this->stylesheet->lookupNamespaceURI("xsl");
								$node = $this->stylesheet->createElementNS($ns, 'include');
								$node->setAttribute('href', $filename);
								if($this->stylesheet->documentElement) {
									$node = $this->stylesheet->documentElement->insertBefore($node, $this->stylesheet->documentElement->firstChild);
								}
							}
						}
					}
				} else {
					foreach (libxml_get_errors() as $error) {
						$this->displayXmlError($error);
					}
					libxml_clear_errors();
					throw new View_Exception('*');
				}

				// 1
				// Загружаем шаблон в процессор
				$this->processor->importStylesheet($this->stylesheet);
				// получаем документ из контроллера и трансформируем его с помошью xslt-процессора	
				$this->document = $this->processor->transformToDoc($this->controller->getXml());
				$errors = libxml_get_errors();
				if($errors) {
					foreach ($errors as $error) {
						$this->displayXmlError($error);
					}
					libxml_clear_errors();
					throw new View_Exception('*');
				}

				$this->document->formatOutput = true;
				$this->document->standalone = false;
				$this->render();
			} catch (View_Exception $e) {
				$content = ob_get_contents();
				ob_end_clean();
				echo $content;
				//Common::alert('<h4>Stylesheet error</h4><br/>', $e->getMessage(), 'error');
			}
		} else {
			$content = ob_get_contents();
			ob_end_clean();
			echo htmlspecialchars_decode($content);
		}
	}
	
	/**
	 *
	 * @param LibXMLErrro $error
	 */
	protected function displayXmlError($error) {
		$title = '';
		switch ($error->level) {
			case LIBXML_ERR_WARNING:
				$title = 'XML Warning: ';
				break;
			case LIBXML_ERR_ERROR:
				$title = 'XML Error: ';
				break;
			case LIBXML_ERR_FATAL:
				$title = 'XML Fatal Error: ';
				break;
		}
		$text = trim($error->message) . PHP_EOL . ' Line: ' . $error->line . PHP_EOL . ' Column: ' . $error->column;

		if ($error->file) {
			$text .= PHP_EOL . ' File: ' . $error->file;
		}	
		Common::alert($title, $text, 'error');
		Maestro_Logger::write_log('error', $text);
	}

	/**
	 *
	 * @param string $filename 
	 */
	public function addIncludeFile($filename) {
		$this->_include_files[] = $filename;
	}

	/**
	 *
	 * @param string $source
	 * @param string $type
	 * @param string $inner 
	 */
	public function prependScript($source, $type = null, $inner = null) {
		$key = count($this->_include_scripts);
		$this->_include_scripts[$key] = array(
			'source' => $source,
			'type' => $type,
			'inner' => $inner,
			'placed' => 'before'
		);
	}

	/**
	 * Если $type=inline, то содержимое файла вставляется в страницу, иначе если есть $inner, то
	 * вставляется содержимое этого параметра
	 * 
	 * @param string $source
	 * @param string $type
	 * @param string $inner 
	 */
	public function appendScript($source, $type = null, $inner = null) {
		$key = count($this->_include_scripts) + 1000;
		$this->_include_scripts[$key] = array(
			'source' => $source,
			'type' => $type,
			'inner' => $inner,
			'placed' => 'before'
		);
	}

	/**
	 * Включает доп.файл в основой файл шаблона
	 *
	 * @param string $filename
	 */
	public function includeFile($filename) {
		
	}

	/**
	 * Дополнительная обработка xslt-шаблона
	 */
	abstract protected function prepare();

	/**
	 * Вывод результатов
	 *
	 */
	protected function render() {
		$this->parse_nodes($this->document);

		//echo $this->getDocType();
		//echo htmlspecialchars_decode($this->document->saveXML($this->document->documentElement));
		if ('xml' == $this->controller->getActionTag('debug')) {
			$class = get_class();
			if ('Maestro_View_Page' == $class) {
				$bg = '#357e9d';
			} else {
				$bg = '#709f14';
			}

			pre(str_replace("\n", "<br>", htmlspecialchars(htmlspecialchars($this->controller->getXml()->saveXML()))), $bg, 'white');
		}

		if ($this->echoNode instanceof DomNode) {
			$content = ob_get_contents();
			ob_end_clean();
			$this->echoNode->parentNode->replaceChild($this->document->createCDATASection($content), $this->echoNode);
		} else {
			$content = ob_get_contents();
			ob_end_clean();
			echo htmlspecialchars_decode($content);
		}

		// доктайп выводим только для страницы
		if ('page' == $this->getMode()) {
			echo $this->getDocType();
		}

		// преобразуем шаблон и документ данных в выходной html-документ
		// и выводим его
		if(method_exists($this->document, 'saveHTML')) {
			echo htmlspecialchars_decode($this->document->saveHTML());
		}
		/* $debug = ob_get_contents();
		  ob_end_clean();

		  echo str_replace('{DEBUG}', $debug, $content); */

		//echo $this->document->saveXML($this->document->documentElement);
	}

	/**
	 * Добавление параметра
	 *
	 * @param string $key ключ
	 * @param string $value значение
	 */
	public function setParam($key, $value) {
		$this->params[$key] = $value;
	}

	/**
	 * Возвращает путь к файлу враппера
	 *
	 * @return string
	 */
	protected function wrapperfile() {
		return Maestro_App::getConfig()->Path->templates . $this->controller->getActionTag('wrapper');
	}

	/**
	 * Возвращает путь к файла слоя относительно файла враппера
	 *
	 * @return string
	 */
	protected function basefile() {
		return $this->controller->getActionTag('layout');
		//return Maestro_App::getConfig()->Path->templates . $this->controller->getActionTag('layout');
	}
	
	/**
	 * Устанавливает полное имя подключаемого файла действия
	 *  
	 * @param string $filename
	 */
	public function setActionFile($filename) {
		$this->_action_file = $filename;
	}

	/**
	 * Возвращает полное имя подключаемого файла действия
	 *
	 * @return string
	 */
	public function getActionFile() {
		if(!$this->_action_file) {
			// определяем имя файла преобразований, подключаемого к базовому
			$this->_action_file = '../modules/'.
					$this->controller->getRouter()->project . '/views/' .
					$this->controller->getRouter()->controller . '.' .
					$this->controller->getRouter()->action . '.xsl';				
			// если файл не существует - берем пустой
			$path = realpath(Maestro_App::getConfig()->Path->templates . $this->_action_file);
			if(!file_exists($path)) {
				return 'action.default.xsl';
			}
		}
		return $this->_action_file;
	}

	/**
	 * Возвращает DocType
	 */
	public function getDocType() {
		//return "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\" \"/xhtml11.dtd\">\n";
		//return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'."\n\n";
		//return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">';
		//return '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html-strict.dtd">'."\n";
		return '<!DOCTYPE html>' . PHP_EOL;

		return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">' . "\n\n";
		#return '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">'."\n";
		#return "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\" \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\n";
		//"<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">\n";
	}

	/**
	 * Добавляет внешний документ в массив встраиваемых документов
	 *
	 * @param string $name
	 * @param Maestro_Form_Interface $obj
	 */
	public function addObject($obj) {
		if ($obj instanceof Maestro_XmlObject_Interface) {
			$name = $obj->getName();
			$this->_imported_objects[$name] = $obj;//->getDocument();
		}
	}
	
	/**
	 * Возвращает встраиваемый объект по имени
	 * 
	 * @param string $name
	 * @return Maestro_XmlObject_Interface 
	 */
	public function getObject($name) {
		return isset($this->_imported_objects[$name]) ? $this->_imported_objects[$name] : null;
	}

	/**
	 * Cканирует выходной xslt-документ в поиске блоков <mf:name>, и выполняет
	 * соответствующий метод-обработчик `render_name`
	 *
	 */
	private function parse_nodes() {
		if(!method_exists($this->document, 'lookupNamespaceURI')) {
			return;
		}
		$mfns = $this->document->lookupNamespaceURI("mf");
		
		$entries = $this->document->getElementsByTagNameNS($mfns, '*');
		$count = $entries->length;
		while ($entries->length && $count) {
			$count--;
			$entry = $entries->item(0);
			$name = 'render_' . $entry->localName;
			//pre($name.' name='.$entry->getAttribute('name').' uri='.$entry->getAttribute('uri'));
			if (method_exists($this, $name)) {
				$this->$name($entry);
			} else {
				$entry->parentNode->removeChild($entry);
			}
		}
	}

	/**
	 * Выполняет действие, укзанное в $uri, результаты вывода берет
	 * из контейнера Maestro_Config с именами:
	 *
	 *  - Widgets=>current (вывод XSLT-преобразования)
	 *  - Widgets=>debug (вывод скрипта php)
	 *
	 * Вывод формируется с помощью view Widget
	 *
	 * @param string $uri
	 * @return string
	 */
	protected function action_direct($uri) {
		//$this->widget_content = false;
		ob_start();
		
		Zend_Registry::set('Widgets.current', false);
		Zend_Registry::set('Widgets.debug', false);

		$router = new Maestro_Router(array('view' => 'widget'));
		$router->run($uri);

		//$this->widget_content 
		$content = htmlspecialchars_decode(ob_get_contents());
		ob_end_clean();
		//Zend_Registry::set('Widgets.debug', $content);
		//$dom = Zend_Registry::get('Widgets.current');
		//return $dom;
		return $content;
	}

	/**
	 *
	 * @param DomNode $node
	 */
	protected function render_widget(DomNode $node) {
		//$this->action_direct($node->getAttribute('uri'));
		//$wnode = $this->document->createTextNode($this->widget_content);
		$wnode = $this->document->createTextNode($this->action_direct($node->getAttribute('uri')));
		$node->parentNode->replaceChild($wnode, $node);
		/*$wname = $node->getAttribute('uri');
		$wcont = $this->action_direct($wname);
		$wnode = $this->document->importNode($wcont->documentElement, true);
		if ($wnode) {
			$node->parentNode->replaceChild($wnode, $node);
			$wnode->insertBefore($this->document->createTextNode(Zend_Registry::get('Widgets.debug')));
		} else {
			$node->parentNode->removeChild($node);
		}*/
	}

	/**
	 * Вставляет в результирующий документ встраиваемый объект
	 *
	 * @param DomNode $tonode
	 */
	protected function render_object($tonode) {
		// получаем имя встраиваемого объекта
		$ename = $tonode->getAttribute('name');
		// получаем сам DomDocument объект
		$edoc = null;
		if (array_key_exists($ename, $this->_imported_objects)) {
			/** @var Maestro_XmlObject_Interface */
			$object = $this->_imported_objects[$ename];
			if($object instanceof Maestro_XmlObject_Interface) {
				$edoc = $object->getDocument();
				$this->_imported_objects[$ename] = $edoc;
			} else if ($object instanceof DOMDocument) {
				$edoc = $object;
			}
		}

		// если объект не определен - просто удаляем текущий элемент <mf:embedding name='...'>
		if (is_null($edoc)) {
			$tonode->parentNode->removeChild($tonode);
		} else {
			$content = htmlspecialchars_decode(htmlspecialchars_decode($edoc->saveHTML()));
			$tonode->parentNode->replaceChild($this->document->createTextNode($content), $tonode);
			//$newnode = $this->document->importNode($edoc->documentElement, true);
			//$tonode->parentNode->replaceChild($newnode, $tonode);
		}
	}

	/**
	 *
	 * @param DomNode $node
	 */
	protected function render_helper(DomNode $node) {
		$name = $node->getAttribute('name');
		$fname = Maestro_App::getConfig()->Path->helpers . $name . '.php';
		if (!file_exists($fname)) {
			$content = '* HELPER NOT FOUND: <strong>' . $name . '</strong> *';
			//pre($content);
			$node->parentNode->replaceChild($this->document->createTextNode($content), $node);
			//$node->parentNode->removeChild($node);
		} else {
			// дополнительные атрибуты узла хелпера передаем в него в виде массива параметров
			$params = array();
			foreach ($node->attributes as $attrName => $attrNode) {
				$params[$attrName] = $attrNode->value;
			}
			
			ob_start();
			$helperDom = new Maestro_XmlObject_Helper();
			$helperDom->setParameters($params);
			$helperDom->setName($name);
			$helperMode = 'txt';
			include $fname;
			$content = ob_get_contents();
			ob_end_clean();
			
			if ('txt' == $helperMode) {
				$node->parentNode->replaceChild($this->document->createTextNode($content), $node);
			} else {
				$this->addObject($helperDom);
				$this->render_object($node);
			}
		}
	}

	/**
	 *
	 * @param DomNode $node
	 */
	protected function render_echo(DomNode $node) {
		$this->echoNode = $this->document->createElement('em');
		$node->parentNode->replaceChild($this->echoNode, $node);
	}

	/**
	 * Добавляем в массив файл скрипта. Если непустой атрибут inline - содержимое файла
	 * при выводе будет вставлено полностью в результат, причем поиск этого файла скрипта будет
	 * производиться не в публичной директории, а в каталоге с шаблонами (/views)
	 * 
	 * <pre>
	 * <mf:javascript src="/js/prototype.js"/> --> <script type="text/javascript" src="/js/prototype.js"></script>
	 * <mf:javascript src="index.news.js" inline="true"/> --> <script type="text/javascript">...</script>
	 * </pre>
	 * 
	 * @param DomNode $node
	 */
	protected function render_javascript(DomNode $node) {
		$this->prependScript(
				$node->getAttribute('src'),
				$node->getAttribute('type'),
				$node->textContent
		);
		$node->parentNode->removeChild($node);
	}

	/**
	 * Подключаем в выходной документ список файлов скриптов. Причем для `inline` скриптов 
	 * включаем в документ содержимое самих файлов.
	 * 
	 * 
	 * @param DomNode $node 
	 */
	protected function render_javascripts(DomNode $node) {
		$outNode = $this->document->createElement('span');
		ksort($this->_include_scripts);
		foreach ($this->_include_scripts as $attributes) {
			$src = $attributes['source'];
			if ('inline' == $attributes['type']) {
				$filepath = Maestro_App::getConfig()->Path->modules .
						$this->controller->getRouter()->project . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . $src;
				if(file_exists($filepath)) {
					$tnode = $outNode->appendChild($this->document->createElement('script'));
					$tnode->setAttribute('type', 'text/javascript');
					$tnode->appendChild($this->document->createTextNode(file_get_contents($filepath)));
					//$node->parentNode->replaceChild($outNode, $node);
				} else {
					pre('Script is not found: '.$src);
				}
			} else if($attributes['inner']) {
				$tnode = $outNode->appendChild($this->document->createElement('script', $attributes['inner']));
				$tnode->setAttribute('type', 'text/javascript');
			} else {
				$tnode = $outNode->appendChild($this->document->createElement('script'));
				$tnode->setAttribute('type', 'text/javascript');
				$tnode->setAttribute('src', $src);
			}
		}
		$node->parentNode->replaceChild($outNode, $node);
		$this->_include_scripts = array();
	}

	/**
	 *
	 */
	public function lockTransformation() {
		$this->_transformationEnable = false;
	}

	/**
	 * Возвращает режим, установленный фабрикой view
	 *
	 * @return string
	 */
	public function getMode() {
		return $this->_mode;
	}

}
