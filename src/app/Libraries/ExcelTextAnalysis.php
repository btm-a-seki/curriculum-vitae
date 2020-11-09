<?php namespace App\Libraries;


class ExcelTextAnalysis
{
    CONST DICTIONARY_PATH = WRITEPATH . 'uploads/vitae/dictionary.json';
    CONST UPLOADS_PATH = WRITEPATH . 'uploads';

    protected $_k = 2;
    protected $_b = 0.75;

    public function __construct(float $k = 2, float $b = 0.75)
    {
        $this->_k = $k;
        $this->_b = $b;
    }

    /**
     * xlsxファイルからセルに記入しているテキストを取得する
     *
     * @param string $target 対象のxlsxのファイル名
     * @return string
     */
    public function extractExcelFile(string $target) : string
    {
        $ret = '';
        $filepath = $target;
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
    public function getWords(string $text, string $kind = '名詞') : array
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
    public function scoreCulculate(array $wordsCount, int $totalCount) : array
    {
        ini_set('memory_limit', '2G');
        ini_set('max_execution_time', 120);
        $result = [];
        $wiki_idf = json_decode(file_get_contents(self::DICTIONARY_PATH), true);
        $avg = $wiki_idf['avg'];
        $idf = array_filter($wiki_idf['idf'], function ($k) use ($wordsCount) {
            return array_key_exists($k, $wordsCount);
        }, ARRAY_FILTER_USE_KEY);
        unset($wiki_idf);

        $result = [];
        foreach ($wordsCount as $word => $count) {
            $result[$word] = [
                'tf' => round($count / $totalCount, 6),
            ];
            $tf = $count / $totalCount;
            // wikipediaのIDFにないものは除外（っていうか0にする）
            if (array_key_exists($word, $idf)) {
                $result[$word]['score'] = round(
                    $idf[$word] * 
                    ($tf * ($this->_k + 1)) / 
                    ($tf + $this->_k * (1 - $this->_b + $this->_b * $totalCount / $avg))
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