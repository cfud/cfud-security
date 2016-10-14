<?php

/*

	Сканнер директорий на изменения
	av 0.0.1
	cfud.biz

*/

class AV {

	////////////////////////////////////////////////////////////////////

	// Полный путь до директории, которую необходимо сканировать
	private $homeDir = '/home/user';

	// Массив полных путей до директорий, которые не будут сканироваться
	private $ignoreDirs = array('/home/user/tmp');

	// Директория для отчетов, если null создается папка по-умолчанию av
	private $reportsDir = null;

	/////////////////////////////////////////////////////////////////////

	private $oldResults, $curResults = array(), $report = array();

	public function __construct() {}

	private function AVcheckReportsDir() {
		$dir = $this->reportsDir;
		if($dir === null) {
			$dir = __DIR__ . DIRECTORY_SEPARATOR . 'av' . DIRECTORY_SEPARATOR;
			$this->reportsDir = $dir;
		}
		$this->ignoreDirs[] = rtrim($dir, DIRECTORY_SEPARATOR);
		if(file_exists($dir) && is_writeable($dir))
			return true;
		elseif(!file_exists($dir))
			return mkdir($dir, 0777);
		return false;
	}

	private function loadOldResults() {
		if(file_exists($this->reportsDir . 'old.report')) {
			$report = file_get_contents($this->reportsDir . 'old.report');
			$report = base64_decode($report);
			$this->oldResults = unserialize($report);
		} else $this->oldResults = null;
	}

	private function scanDirProccess($dir) {
		if(is_dir($dir) && !in_array($dir, $this->ignoreDirs)) {
			$this->curResults[md5($dir)] = array('type'=>'dir', 'path'=>$dir);
			$scan = scandir($dir);
			foreach($scan as $fileName) {
				if($fileName != '.' && $fileName != '..') {
					$path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
					$this->scanDirProccess($path);
				}
			}
		} 
		if(is_file($dir)) {
			$this->curResults[md5($dir)] = array('type'=>'file', 'path'=>$dir, 'md5'=>md5_file($dir));
		}
	}

	private function saveOldReport() {
		$report = serialize($this->curResults);
		$report = base64_encode($report);
		file_put_contents($this->reportsDir . 'old.report', $report);
	}

	private function writeNewReport() {
		if(count($this->report)) file_put_contents($this->reportsDir . 'report_' . date("Y-m-d_H-i-s") . '.txt', implode(PHP_EOL, $this->report), FILE_APPEND);
		else file_put_contents($this->reportsDir . 'report_' . date("Y-m-d_H-i-s") . '_CLEAN.txt', '');
	}

	public function startScan() {
		if(file_exists($this->homeDir) && is_dir($this->homeDir) && $this->AVcheckReportsDir()) {
			$this->loadOldResults();
			$this->scanDirProccess($this->homeDir);
			if($this->oldResults !== null) {
				foreach($this->curResults as $dir) {
					if(!array_key_exists(md5($dir['path']), $this->oldResults)){
						if($dir['type'] == 'dir') $this->report[] = "Новая директория [".$dir['path']."]";
						else $this->report[] = "Новый файл [".$dir['path']."] контрольная сумма [".$dir['md5']."]";
					} elseif($dir['type'] == 'file') {
						$oldFile = $this->oldResults[md5($dir['path'])];
						if($oldFile['md5'] != $dir['md5']) $this->report[] = "Изменен файл [".$dir['path']."] контрольная сумма [".$dir['md5']."]";
					}
				}
				$this->writeNewReport();
			}
			$this->saveOldReport();
		}
	}

}

$scanner = new AV();
$scanner->startScan();

# cfud.biz