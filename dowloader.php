<?php
	session_start();

	class Downloader {
		public $new_dir = "lesson_files";
		public $dir;

		function __construct($arg) {
			$this->dir = $arg;
		}

		public function myscandir($link, $sort=0) {
			$list = scandir($link, $sort);

			if (!$list) return false;

			if ($sort == 0) unset($list[0],$list[1]);
			else unset($list[count($list)-1], $list[count($list)-1]);
			return $list;
		}

		public function getNameAudio($arg) {
			include("bd.php");
			$select_audios = $mysqli->query("SELECT name, url FROM `audios` WHERE `url` LIKE '%".$arg."'");
			$data_select_audios = $select_audios->fetch_array(MYSQLI_ASSOC);

			return $data_select_audios;
		}

		public function fenerateZip() {
			$files = $this->myscandir($this->new_dir);

			$zip = new ZipArchive();
			$zip_name = time().'_'.$_SESSION['id'].".zip"; // имя файла

			if($zip->open($zip_name, ZIPARCHIVE::CREATE)!== TRUE) {
				echo "Файл не создан, попробуйте позже";
			}

			foreach($files as $file) {
				$zip->addFile($this->new_dir.DIRECTORY_SEPARATOR.$file); // файлы в zip архив
			}

				$zip->close();

			if(file_exists($zip_name)) {
				echo $zip_name;
			}
		}

		public function buildFile() {
			if(is_dir($this->new_dir)) {
				$files_del = $this->myscandir($this->new_dir);

				foreach($files_del as $file) {
					unlink($this->new_dir.DIRECTORY_SEPARATOR.$file);
				}

				rmdir($this->new_dir);
			}

			mkdir($this->new_dir, 0777);


			$files = $this->myscandir($this->dir);

			foreach($files as $file) {
				preg_match('/(\.[a-z0-9]*)/m', $file, $format);
				copy($this->dir.$file, $this->new_dir.DIRECTORY_SEPARATOR.$this->getNameAudio($file)['name'].$format[0]);
			}

			if(isset($_POST['path_pdf']) && $_POST['path_pdf'] != '') {
				copy($_POST['path_pdf'], $this->new_dir.DIRECTORY_SEPARATOR.basename($_POST['path_pdf']));
			}

			if(isset($_POST['manual']) && $_POST['manual'] != '') {
				copy($_POST['manual'], $this->new_dir.DIRECTORY_SEPARATOR.basename($_POST['manual']));
			}

			$this->generateZip();
		}
	}
