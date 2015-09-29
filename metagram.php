<?php

class DictionaryParser {

  /**
   * Имена аргументов командной строки
   */
  const DICT_ARGNAME       = "-dict";

	const FIRST_WORD_ARGNAME  = "-start";

	const LAST_WORD_ARGNAME   = "-end";

  /**
   * Тексты сообщений об ошибках
   */
  const ARGS_ERROR_MESSAGE = <<<EOT
Пример использования:
php metagram.php -dict словарь.txt -start слово1 -end слово2
EOT;

  const WORD_LENGTH_ERROR = 'Длина начального и конечного слов должна быть одинаковой!';

  const FILE_NOT_EXISTS_ERROR = 'Ошибка: указанный файл не найден';

  /**
   * Имя файла словаря
   * @var string
   */
  private $dictFileName;

  /**
   * Первое слово
   * @var string
   */
  private $firstWord;

  /**
   * Слово, которое мы хотим получить в итоге
   * @var string
   */
  private $lastWord;

  /**
   * Слова заданной длины, найденные в словаре
   * @var array
   */
  private $words;

	public function __construct($commandLineArgs) {
		$dictArgIndex   = array_search(self::DICT_ARGNAME, $commandLineArgs);
    $firstWordIndex = array_search(self::FIRST_WORD_ARGNAME, $commandLineArgs);
    $lastWordIndex  = array_search(self::LAST_WORD_ARGNAME, $commandLineArgs);
    if (!($dictArgIndex !== false && $firstWordIndex !== false && $lastWordIndex !== false)) {
      $this->showMessage(self::ARGS_ERROR_MESSAGE);
    }

    $dictFileName = $commandLineArgs[$dictArgIndex + 1];
    $wordStart    = $commandLineArgs[$firstWordIndex + 1];
    $wordEnd      = $commandLineArgs[$lastWordIndex + 1];

    if (mb_strlen($wordStart) != mb_strlen($wordEnd)) {
      $this->showMessage(self::WORD_LENGTH_ERROR);
    }

    if (!file_exists($dictFileName)) {
      $this->showMessage(self::FILE_NOT_EXISTS_ERROR);
    }

    $this->dictFileName = $dictFileName;
    $this->firstWord    = mb_convert_case($wordStart, MB_CASE_LOWER, 'UTF-8');
    $this->lastWord     = mb_convert_case($wordEnd, MB_CASE_LOWER, 'UTF-8');
	}

  /**
   * Выводит заданное сообщение об ошибке и останавливает
   * выполнение скрипта, если $terminate=true
   *
   * @param string $msg
   * @param bool $terminate
   */
  private static function showMessage($msg, $terminate = true) {
    echo $msg . PHP_EOL;
    if ($terminate) {
      exit();
    }
  }

  /**
   * Ищет в словаре слова заданной длины
   */
  public function processDictionary() {
    $dict = fopen($this->dictFileName, 'r');
    $wordLength = mb_strlen($this->firstWord, 'UTF-8');
    $words = array();
    $this->showMessage("Ищем в словаре слова длиной " . $wordLength . " символов... ", false);
    while (($line = fgets($dict)) !== false) {
      if (mb_strlen(trim($line), 'UTF-8') == $wordLength) {
        array_push($words, trim($line));
      }
    }
    $this->showMessage("Найдено " . count($words) . " слов.", false);
    fclose($dict);
    $this->words = $words;
  }

  public function getFirstWord() {
    return $this->firstWord;
  }

  public function getLastWord() {
    return $this->lastWord;
  }

  public function getWords() {
    return $this->words;
  }
}

class Tree {

  /**
   * @var Tree||null
   */
  public $parent = null;

  /**
   * @var string
   */
  public $name = null;

  /**
   * @var Tree[]
   */
  public $children = array();

  public function __construct($name, $parent = null) {
    $this->name = $name;
    $this->parent = $parent ? $parent : null;
  }

  public function getPath() {
    if ($this->parent) {
      return $this->parent->getPath() . ' -> ' . $this->name;
    } else {
      return $this->name;
    }
  }

  public function addChild($child) {
    array_push($this->children, $child);
  }

  public function setChildren($children) {
    $this->children = $children;
  }
}

/**
 * Левенштейн для UTF-8 строк будет работать неправильно,
 * поэтому сделаем такой простенький вариант:
 * фнукцию, возвращающую число несовпадающих элементов
 * массивов arr1 и arr2
 *
 * @param array $arr1
 * @param array $arr2
 * @return int
 */
function getDiffCharsNum($arr1, $arr2) {
  $counter = 0;
  foreach ($arr1 as $key => $char) {
    if ($char != $arr2[$key]) $counter += 1;
  }
  return $counter;
}

/**
 * Возвращает массив строк, отличающихся от заданного
 * $wordTree на один символ. Найденные строки, как и само
 * слово, удаляются из массива $wordTrees
 *
 * @param $wordTree
 * @param $words
 * @return array
 */
function getNeighbors(Tree $wordTree, &$words) {
  if(($key = array_search($wordTree->name, $words)) !== false) {
    unset($words[$key]);
  }
  $chars = preg_split('//u', $wordTree->name, -1, PREG_SPLIT_NO_EMPTY);
  $neighbors = array();

  foreach ($words as $key => $value) {
    $otherChars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
    if (getDiffCharsNum($chars, $otherChars) == 1) {
      array_push($neighbors, $value);
      unset($words[$key]);
    }
  }
  return $neighbors;
}

function buildTree($word, &$words, $lastWord, $parent = null) {
  $tree = new Tree($word);
  if ($parent) {
    $tree->parent = $parent;
  }
  $children = getNeighbors($tree, $words);
  if (count($children)) {
    if (array_search($lastWord, $children) !== false) {
      echo $tree->getPath() . ' -> ' . $lastWord . PHP_EOL;
    }
    $tree->setChildren($children);
    foreach ($children as $child) {
      buildTree($child, $words, $lastWord, $tree);
    }
  }
}

$dictParser = new DictionaryParser($argv);
$dictParser->processDictionary();

$firstWord = $dictParser->getFirstWord();
$lastWord = $dictParser->getLastWord();
$words = $dictParser->getWords();

buildTree($firstWord, $words, $lastWord);

exit();