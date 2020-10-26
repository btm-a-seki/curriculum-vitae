<?php namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CodeIgniter;

class MergeDictionary extends BaseCommand
{
    protected $group       = 'CurriculumVitae';
    protected $name        = 'dictionary:merge';
    protected $description = '分割作成した辞書ファイルをマージします。';

    const TARGET_DIRECTORY = 'uploads/';
    const TARGET_BASE_FILENAME = 'dictionary';

    private $_idfDictionary = [];

    public function run(array $params)
    {
        ini_set('memory_limit', '2G');
        $startTime = microtime(true);
        // ヘルパーの読み込み
        helper('filesystem');

        $dirpath = WRITEPATH . self::TARGET_DIRECTORY;
        $filenames = get_filenames($dirpath);
        $dicCount = 0;
        foreach ($filenames as $filename) {
            $dicCount += $this->_mergeDictionary($dirpath, $filename);
        }
        $wordCount = array_sum($this->_idfDictionary);
        $this->_createIdf($dicCount);
        write_file($dirpath . 'dictionary.json', json_encode(['avg' => $wordCount / $dicCount,'idf' => $this->_idfDictionary]), JSON_UNESCAPED_UNICODE);
    }

    private function _mergeDictionary($dirpath, $filename)
    {
        if (!preg_match('/^dictionary[0-9]+.json$/u', $filename)) {
            return 0;
        }
        $filepath = $dirpath . $filename;
        $dic = json_decode(file_get_contents($filepath), true);
        if (!isset($dic['w_c'])) {
            return 0;
        }
        foreach ($dic['w_c'] as $word => $count) {
            $this->_idfDictionary[$word] = isset($this->_idfDictionary[$word]) ? $this->_idfDictionary[$word] + $count : $count;
        }

        return $dic['c'];
    }

    /**
     * BM25のIDFを算出し設定する。
     *
     * @param integer $totalDocCount 全文書数
     * @return void
     */
    private function _createIdf(int $totalDocCount)
    {
        foreach ($this->_idfDictionary as $word => $count) {
            $this->_idfDictionary[$word] = log(($totalDocCount - $count + 0.5) / ($count + 0.5));
        }
    }
}