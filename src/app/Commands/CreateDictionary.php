<?php namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CodeIgniter;

class CreateDictionary extends BaseCommand
{
    protected $group       = 'CurriculumVitae';
    protected $name        = 'dictionary:create';
    protected $description = 'WikipediaのダンプからIDF辞書を作成します。';

    const TARGET_DIRECTORY = 'uploads/';
    const DICTIONARY_FILENAME = 'dictionary.json';
    const SKIP_DOC_COUNT = 0;

    private $_idfDictionary = [];

    public function run(array $params)
    {
        ini_set('memory_limit', '2G');
        $startTime = microtime(true);
        // ヘルパーの読み込み
        helper('filesystem');

        // 対象のファイルの作成
        $dirPath = WRITEPATH . self::TARGET_DIRECTORY;
        $targetFile = $this->_getTargetFile($dirPath);
        $filesize = get_file_info($dirPath . $targetFile, 'size')['size'];
        // 辞書作成開始
        $fp = fopen($dirPath . $targetFile, 'r');
        $start = false;
        $body = '';
        $count = 0;
        $docNounsCount = [];
        $size = 0;
        // 1行ずつ読み取って作業をする
        while (($row = fgets($fp)) !== false) {
            $size += strlen($row);
            // XMLファイルの<page>から</page>までが一つの記事のため、
            // そこからそこまでを取得して作業を行う。
            if (preg_match('/^\s*?<page>\s*$/', $row)) {
                $start = true;
                $body = '';
            }
            if ($start) {
                $body .= $row;
            }
            if (preg_match('/^\s*?<\/page>\s*$/', $row)) {
                CLI::showProgress($size, $filesize); // 進捗率更新
                $start = false;
                $count++;
                if ($count <= self::SKIP_DOC_COUNT) {
                    continue;
                }
                $ret = $this->_extractWikipediaDocument($body);
                if ($ret) $docNounsCount[] = $ret;
                // if ($count % 100000 == 0) {
                //     write_file($dirPath . sprintf('dictionary%d.json', $count / 100000), json_encode(['c' => count($docNounsCount), 'w_c' => $this->_idfDictionary]));
                //     unset($this->_idfDictionary);
                //     $this->_idfDictionary = [];
                // }
            }
        }
        fclose($fp);
        // write_file($dirPath . sprintf('dictionary%d.json', ceil($count / 100000)), json_encode(['c' => count($docNounsCount), 'w_c' => $this->_idfDictionary]));
        $this->_createIdf(count($docNounsCount));
        write_file($dirPath . self::DICTIONARY_FILENAME, json_encode(['avg' => array_sum($docNounsCount) / count($docNounsCount),'idf' => $this->_idfDictionary]));
        CLI::showProgress(false);
        CLI::write('実行時間 : ' . (microtime(true) - $startTime));
        CLI::write('総単語数 : ' . count($this->_idfDictionary));
    }

    /**
     * 辞書作成の対象ファイル名を取得する
     *
     * @param string $dirPath
     * @return string
     */
    private function _getTargetFile(string $dirPath) : string
    {
        // 最新の日付のダンプを使う
        $targetFile = '';
        $dumpDate = '';
        foreach (get_filenames($dirPath) as $filename) {
            $match = [];
            if (preg_match('/^jawiki-([0-9]{8})-/', $filename, $match)) {
                if ($dumpDate < $match[1]) {
                    $dumpDate = $match[1];
                    $targetFile = $filename;
                }
            }
        }
        return $targetFile;
    }

    /**
     * XMLエレメントにしたWikipediaのドキュメントから本文を抽出し、
     * 本文に含まれる単語数を返す
     *
     * @param string $document
     * @return integer
     */
    private function _extractWikipediaDocument(string $body) : int
    {
        $document = simplexml_load_string(
            $body,
            'SimpleXMLElement',
            LIBXML_COMPACT | LIBXML_NONET
        );
        // NOTE: Wikipedia独自の記事の削除（いらないかも）
        // if (preg_match('/^(Wikipedia|特別):/u', $document->title)) {
        //     CLI::write('Skip : ' . $document->title);
        //     return 0;
        // }
        $documentOfWord = $this->_executeMecab($document->revision->text);
        return $documentOfWord;
    }

    /**
     * Mecabを実行し、実行したテキストの単語数を取得する。
     *
     * @param string $body
     * @return integer
     */
    private function _executeMecab(string $body) : int
    {
        $body = str_replace(['\\', '"', '%', '`', '$'], ['\\\\', '\"', '\%', '\`', '\$'], strip_tags($body));
        // Mecab実行
        $cmd = 'echo "' . $body . '"';
        $cmd .= ' | mecab -b 1000000 -d /usr/lib/x86_64-linux-gnu/mecab/dic/mecab-ipadic-neologd';
        $cmd .= ' | grep "名詞,"'; // 名詞のみを取得する
        $outputs = $words = [];
        $wordsCount = 0;
        exec($cmd, $outputs);
        foreach ($outputs as $output) {
            // 実行結果から余計な文字を削除し、単語を設定する
            $split = explode(',', $output);
            $word = preg_replace('/\s+名詞$/u', '', $split[0]);
            $words[] = $word;
            $wordsCount++;
        }
        foreach (array_unique($words) as $word) {
            isset($this->_idfDictionary[$word]) ? $this->_idfDictionary[$word]++ : $this->_idfDictionary[$word] = 1;
        }
        return $wordsCount;
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