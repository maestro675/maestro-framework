<?php

/**
 * Maestro_Dom: класс,расширяющий базовый класс DomDocument
 * дополнительными функциями
 *
 * @author Sergey Lysiansky, s.lysiansky@ucb-ua.com
 * @version    2.0
 */
class Maestro_Dom extends DomDocument {
	const ROOT = 'root';
	const IDENTIFICATOR = 'id';
	const DEFAULT_FNAME = 'NAME';

	/**
	 * добавляет в узео $parent новый дочерний узел с именем $name
	 *
	 * @param mixed  $parent родительский узел или 'root'(корневой узел)
	 * @param string $name имя узла
	 * @param string $value содержимое узла
	 * @param string $id атрибут id=""
	 *
	 * @return DomNode
	 */
	public function addNode($parent, $name, $value='', $id='') {
		if (self::ROOT == $parent || is_null($parent)) {
			$parent = $this->documentElement;
		}

		if(is_array($value)) {
			$node = $parent->appendChild($this->createElement($name));
			$this->addArray($node, $value);
		} else if (is_object($value)) {
			$node = $this->addUnitObject($parent, $value, $name);
		} else if (empty($value) || is_null($value)) {
			if(is_numeric($name)) {
				throw new Maestro_Exception('Node name must be a string: ' . $name);
			}
			$node = $parent->appendChild($this->createElement($name));
		} else {
			// если строка большая - добавим в виде CDATA
			if(mb_strlen($value) >= 255) {
				$node = $this->addNodeCDATA($parent, $name, $value);
			} else {
				$node = $parent->appendChild($this->createElement($name, htmlspecialchars($value)));
			}
			//$node = $parent->appendChild($this->createElement($name, $value));
		}

		if (!empty($id) || '0' == $id) {
			$node->setAttribute(self::IDENTIFICATOR, $id);
		}

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
	public function addNodeCDATA($parent, $name, $cdata, $id='') {
		$node = $this->addNode($parent, $name, '', $id);
		$text = $this->createCDATASection(htmlspecialchars_decode($cdata));
		$node->appendChild($text);
		return $node;
	}

	/**
	 *
	 * @param object|string $ownNode DomNode object or string name of new node
	 * @param array $units of Maestro_Unit objects
	 * @param string $nodename
	 */
	public function addUnitObjects($ownNode, $units, $nodename = 'object') {
		if (!is_object($ownNode) && $ownNode <> self::ROOT) {
			$ownNode = $this->addNode(self::ROOT, $ownNode);
		}

		if ($units instanceof Zend_Db_Table_Rowset_Abstract) {
			$units = $units->toArray();
		} elseif (!is_array($units)) {
			return;
		}
		
		foreach ($units as $u) {
			$this->addUnitObject($ownNode, $u, $nodename);
		}
	}

	/**
	 *
	 * @param DomNode $ownnodem
	 * @param maestro_Unit $u
	 * @param string $name
	 */
	public function addUnitObject($ownNode, $u, $name) {
		if ($u instanceof Maestro_Unit) {
			$fields = $u->vars();
			$id = $u->id;
		} elseif ($u instanceof stdClass) {
			$fields = (array) $u;
			$id = isset($u->id) ? $u->id : NULL;
		} elseif ($u instanceof Maestro_DateTime) {
			$this->addNode($ownNode, $name, $u->format('d.m.Y H:i')); // $u->humanity());
			return;
		} elseif ($u instanceof Zend_Db_Table_Row_Abstract) {
			$fields = $u->toArray();
			$id = $u->id;
		} elseif (is_array($u)) {
			$fields = $u;
			$id = isset($u['id']) ? $u['id'] : NULL;
		} else {
			return;
		}
		$node = $this->addNode($ownNode, $name, '', $id);
		foreach ($fields as $alias => $value) {
			$this->addNode($node, $alias, $value);
		}
		return $node;
	}

	/**
	 * Добавляем массив в документ.
	 * Если ключ элемента массива - число, добавляется узел с именем, заданным $name,
	 * и ключ заносится в атрибут `id`.
	 * Если ключ массива имеет вид `key:id`, то добавляется узел с именем `key` и атрибутом `id`.
	 *
	 * Вложенные массивы обрабатываются рекурсивно.
	 *
	 * @param DomNode $ownNode родительский узел
	 * @param array $a входящий массив
	 * @param string $name имя узла по умолчанию
	 */
	public function addArray($ownNode, $a, $name = 'item', $checks = array(), $strong_mode = false) {
		if (!is_array($a)) {
			return;
		}
		foreach ($a as $key => $value) {
			$id = NULL;
			if (is_numeric($key) || empty($key) || $strong_mode) {
				$id = $key;
				$key = $name;
			} else {
				$ak = explode(':', $key, 2);
				if (count($ak) > 1) {
					$key = $ak[0];
					$id = $ak[1];
				}
			}

			if (is_array($value)) {
				$node = $this->addNode($ownNode, $key, NULL, $id);
				$this->addArray($node, $value);
			} else {
				$node = $this->addNode($ownNode, $key, $value, $id);
				if(is_array($checks)) {
					if(array_key_exists($id, $checks)) {
						$node->setAttribute('checked', true);
					}
				} else if($checks == $id) {
						$node->setAttribute('checked', true);
				}
			}
		}
	}

	/**
	 * добавляет в узел $ownode  массив элементов из масива $data
	 * аттрибут id - первичный ключ массива, содержимое - вторичный ключ $nkey
	 *
	 * @param object $ownNode родительский узел
	 * @param array $data массив данных
	 * @param string $nkey название поля значения
	 * @param string $level уровень вложенности
	 */
	public function addItems($ownNode, $data, $nkey=self::DEFAULT_FNAME, $level=0) {
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
			$this->addNode($ownNode, 'item', $prefix . $value, $id);
			// для дочерних элементов
			if (isset($info['childNodes']) && count($info['childNodes']))
				$this->addItems($ownNode, $info['childNodes'], $nkey, $level + 1);
		}
	}

}
