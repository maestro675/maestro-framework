<?php

/**
 * Description of Abstract
 *
 * @author maestro
 */
abstract class Maestro_Controller_Abstract {

	/**
	 * документ для формирования данных, отдаваемых view
	 *
	 * @var Maestro_Dom
	 */
	protected $xml;

	/**
	 * Ссылка на роутер
	 *
	 * @var Maestro_Router
	 */
	protected $_front;

	/**
	 * экземпляр класса вывода
	 *
	 * @var Maestro_View_Base
	 */
	protected $view;

	/**
	 * Массив значений разрешенных тэгов, указанных в действии (action)
	 *
	 * @var array
	 */
	protected $_actionTags;

	/**
	 * Конструктор класса
	 *
	 * @param Maestro_Router $front
	 * @param array $action_tags
	 */
	public function __construct($front, $action_tags) {
		$this->_front = $front;
		$this->_actionTags = $action_tags;

		$this->xml = new Maestro_Dom('1.0', 'UTF-8');
		$this->xml->formatOutput = true;
		$docNode = $this->AddNode($this->xml, 'document');
		$docNode->setAttribute('route', $this->getRouter()->getRoute());
		$docNode->setAttribute('enviroment', APPLICATION_ENV);

		// блок общей информации
		/* $node = $this->xml->addNode(Maestro_Dom::ROOT, 'mvc');
		  $this->xml->addNode($node, 'project', $this->_front->project);
		  $this->xml->addNode($node, 'controller', $this->_front->controller);
		  $this->xml->addNode($node, 'action', $this->_front->action);
		  $this->xml->addNode($node, 'route', $this->_front->route);
		  $this->xml->addNode($node, 'route', $this->_front->route); */

		// блок тэгов действия контроллера
		// -- перенесено в process --
		/* $node = $this->xml->addNode(Maestro_Dom::ROOT, 'actiontags');
		  if(is_array($action_tags))
		  foreach($action_tags as $name => $value)
		  {
		  $this->xml->addNode($node, $name, $value);
		  } */
	}

	/**
	 * Возвращает ссылку на документ
	 *
	 * @return Maestro_Dom
	 */
	public function getXml() {
		return $this->xml;
	}
	
	/**
	 *
	 * @return Maestro_View_Base
	 */
	public function getView() {
		return $this->view;
	}

	/**
	 *
	 * @return Maestro_Router
	 */
	public function getRouter() {
		return $this->_front;
	}

	/**
	 *
	 */
	protected function invoke(/* ... */) {
		$args = func_get_args();
		$num_args = count($args);

		if ($num_args <= 0) {
			return false;
		}

		$method = array_shift($args);

		if (method_exists($this, $method)) {
			return call_user_func_array(array($this, $method), $args);
		}

		return true;
	}

	/**
	 * добавляет в узео $parent новый дочерний узел с именем $name
	 *
	 * @param mixed  $parent родительский узел или 'root'(корневой узел)
	 * @param string $name имя узла
	 * @param string $value содержимое узла
	 * @param string $id атрибут id=""
	 */
	public function &AddNode($parent, $name, $value = '', $id = '') {
		if ($parent == 'root')
			$parent = $this->xml->documentElement;
		if (empty($value)) {
			$node = $parent->appendChild($this->xml->createElement($name));
		} else {
			$node = $parent->appendChild($this->xml->createElement($name, $value));
		}
		if (!empty($id))
			$node->setAttribute('id', $id);
		return $node;
	}

	/**
	 * добавляет в узео $parent новый дочерний узел, содержащий блок CDATA
	 * с именем $name
	 *
	 * @param mixed  $parent родительский узел или 'root'(корневой узел)
	 * @param string $name имя узла
	 * @param string $cdata содержимое узла
	 * @param string $id атрибут id=""
	 */
	public function &AddNodeCDATA($parent, $name, $cdata, $id = '') {
		$node = $this->AddNode($parent, $name, '', $id);
		$text = $this->xml->createCDATASection(htmlspecialchars_decode($cdata));
		$node->appendChild($text);
		return $node;
	}

	/**
	 * добавляет в узел $ownode  массив элементов из масива $data
	 * аттрибут id - первичный ключ массива, содержимое - вторичный ключ $nkey
	 *
	 * @param object $ownode родительский узел
	 * @param array $data массив данных
	 * @param string $nkey название поля значения
	 * @param string $nname название узла
	 * @param string $level уровень вложенности
	 */
	public function AddItems($ownode, &$data, $nkey = 'NAME', $nname = 'item', $level = 0) {
		$prefix = '';
		for ($n = 0; $n < $level; $n++)
			$prefix .= "&#xa0;|&#xa0;&#xa0;&#xa0;&#xa0;";
		foreach ($data as $id => $info) {
			// значение по ключу
			if ($nkey == 'self')
				$value = $info;
			else
			if (array_key_exists($nkey, $info))
				$value = $info[$nkey];
			else
				$value = '- mismatch key -';
			// добавляем узел
			$this->AddNode($ownode, $nname, $prefix . $value, $id);
			// для дочерних элементов
			if (isset($info['childNodes']) && count($info['childNodes']))
				$this->addItems($ownode, $info['childNodes'], $nkey, $nname, $level + 1);
		}
	}

}

?>
