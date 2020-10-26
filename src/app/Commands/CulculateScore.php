<?php namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CodeIgniter;

class CulculateScore extends BaseCommand
{
    protected $group       = 'CurriculumVitae';
    protected $name        = 'score';
    protected $usage       = 'score TargetFileName [-k 2.0] [-b 0.75] [-l 20]';
    protected $description = 'BM25でxlsx形式の文書内の単語の重要度を算出します。' . PHP_EOL . '対象のファイルは writable/uploads/vitae 以下のものとなる。';

    protected $options = [
        '-k' => 'TF-IDF値の影響の大きさを調整するパラメータ。1.2もしくは2.0を設定する。設定しない場合は2.0となる。',
        '-b' => '文書の単語数による影響の大きさを調整するパラメータ。0.0から1.0の間で設定する。設定しない場合は0.75となる。',
        '-l' => '一覧表示する件数。設定しない場合は全件表示する。',
    ];

    protected $_k = 2.0;
    protected $_b = 0.75;

    const TARGET_DIRECTORY = 'uploads/vitae/';
    const DICTIONARY_FILEPATH = 'uploads/vitae/dictionary.json';

    public function run(array $params)
    {
        ini_set('memory_limit', '2G');
        $start = microtime(true);

        $options = CLI::getOptions();
        if (isset($options['k'])) {
            $this->_k = $options['k'];
        }
        if (isset($options['b'])) {
            $this->_b = $options['b'];
        }
        
        if (count($params) === 0) {
            CLI::write('対象のファイルを選択してください。', 'red');
            return;
        }
        $target = $params[0];

        $result = $this->_culculate($target);
        CLI::write("実行時間 : " . (microtime(true) - $start));
        // echo "総単語数 : {$wordCount}（{$totalCount}）" . PHP_EOL;
        $count = 0;
        foreach ($result as $word => $data) {
            CLI::write("  $word : {$data['score']} ({$data['tf']})");
            if (!empty($options['l']) && ++$count >= $options['l']) {
                break;
            }
        }
    }

    /**
     * xlsxファイルからBM25による単語の重要度を計算する
     *
     * @param string $target
     * @return array
     */
    private function _culculate(string $target) : array
    {
        $vitaeBody = $this->_extractExcelFile($target);
        list($wordsCount, $totalCount) = $this->_getWords($vitaeBody);
        return $this->_scoreCulculate($wordsCount, $totalCount);
    }

    // TODO: これ以下はライブラリとかにした方がいいと思ってる。
    /**
     * xlsxファイルからセルに記入しているテキストを取得する
     *
     * @param string $target 対象のxlsxのファイル名
     * @return string
     */
    private function _extractExcelFile(string $target) : string
    {
        $ret = '';
        $filepath = WRITEPATH . self::TARGET_DIRECTORY . $target;
        if (!file_exists($filepath)) {
            throw new \Exception('target file not found');
        }
        $zip = zip_open($filepath);
        $xlsx = [];
        while (($zipFile = zip_read($zip)) !== false) {
            if (!zip_entry_open($zip, $zipFile)) {
                continue;
            }
            $zip_name = zip_entry_name($zipFile);
            // 特定のxmlファイルのみ読み込む
            // TODO: 複数シートある場合の対策が必要
            if ($zip_name != 'xl/sharedStrings.xml') {
                continue;
            }
            // 対象のファイルからデータを全て読み込む
            $xlsx[$zip_name] = '';
            while (($zipText = zip_entry_read($zipFile))) {
                $xlsx[$zip_name] .= $zipText;
            }
            // 読み仮名と【ほげ】を削除、改行を半スぺに
            $xlsx[$zip_name] = preg_replace(['/<rPh.*<\/rPh>/u', '/【.+】/u'], '', $xlsx[$zip_name]);
            $xlsx[$zip_name] = preg_replace(['/\s+/', '/\(/', '/\)/'], ' ', $xlsx[$zip_name]);
            $xlsx[$zip_name] = preg_replace('/<[0-9a-zA-Z \/"=:.?-]+>/u', ' ', $xlsx[$zip_name]);
            $ret .= $xlsx[$zip_name];
        }
        return $ret;
    }

    /**
     * 本文から単語を分かち書きして結果を取得する
     *
     * @param string $text
     * @param string $kind
     * @return array
     */
    private function _getWords(string $text, string $kind = '名詞') : array
    {
        $text = str_replace(['\\', '"', '%', '`', '$'], ['\\\\', '\"', '\%', '\`', '\$'], strip_tags($text));
        // Mecab実行
        $cmd = 'echo "' . $text . '"';
        $cmd .= ' | mecab -b 1000000 -d /usr/lib/x86_64-linux-gnu/mecab/dic/mecab-ipadic-neologd';
        if (!empty($kind)) {
            $cmd .= ' | grep "' . $kind . ',"';
        }
        exec($cmd, $outputs);
        foreach ($outputs as $output) {
            // 実行結果から余計な文字を削除し、単語を設定する
            $split = explode(',', $output);
            $word = preg_replace('/\s+名詞$/u', '', $split[0]);
            $words[] = $word;
        }
        return [
            array_count_values($words),
            count($words),
        ];
    }

    /**
     * BM25のスコアを計算する
     *
     * @param array $wordsCount キー：単語、値：出現数の配列
     * @param integer $totalCount 文書内の単語の総数
     * @return array
     */
    private function _scoreCulculate(array $wordsCount, int $totalCount) : array
    {
        $result = [];
        $wiki_idf = json_decode(file_get_contents(WRITEPATH . self::DICTIONARY_FILEPATH), true);

        $result = [];
        foreach ($wordsCount as $word => $count) {
            $result[$word] = [
                'tf' => round($count / $totalCount, 6),
            ];
            $tf = $count / $totalCount;
            // wikipediaのIDFにないものは除外（っていうか0にする）
            if (array_key_exists($word, $wiki_idf['idf'])) {
                $result[$word]['score'] = round(
                    $wiki_idf['idf'][$word] * 
                    ($tf * ($this->_k + 1)) / 
                    ($tf + $this->_k * (1 - $this->_b + $this->_b * $totalCount / $wiki_idf['avg']))
                    , 6);
            } else {
                $result[$word]['score'] = 0;
            }
            
        }
        // 降順に並び替え
        uasort($result, function ($a, $b) {
            return $a['score'] < $b['score'];
        });

        return $result;
    }
}