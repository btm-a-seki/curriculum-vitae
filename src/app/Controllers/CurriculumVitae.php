<?php namespace App\Controllers;

use App\Libraries\ExcelTextAnalysis;

class CurriculumVitae extends BaseController
{
    CONST DEFAULT_LIST_ROWS = 20;

    public function index()
    {
        echo 'Hello World!';
    }

    public function show(string $name = '')
    {
        var_dump($name);
    }

    public function update()
    {
        //
    }

    public function calc()
    {
        // パラメータ設定
        $rows = $this->request->getPost('rows') ?? self::DEFAULT_LIST_ROWS;
        if (!is_int($rows)) {
            throw new \InvalidArgumentException('rowsは数値で指定してください');
        }
        $file = $this->request->getFile('culculate_file');
        // 送信されたファイルの処理
        if (empty($file)) {
            throw new \InvalidArgumentException('culculate_fileが選択されてません');
        }
        if (!$file->isValid()) {
            throw new \InvalidArgumentException($file->getErrorString().'('.$file->getError().')');
        }
        if (!in_array($file->getExtension(), ['xlsx'])) {
            throw new \InvalidArgumentException('対応していない拡張子です。');
        }
        $tempName = $file->getRandomName();
        $uploadPath = ExcelTextAnalysis::UPLOADS_PATH . '/vitae';
        $file->move($uploadPath, $tempName, true);

        $excelTextAnalysis = new ExcelTextAnalysis();
        $filename = $uploadPath . '/' . $tempName;
        $body = $excelTextAnalysis->extractExcelFile($filename);
        list($words, $count) = $excelTextAnalysis->getWords($body);
        $scores = $excelTextAnalysis->scoreCulculate($words, $count);
        if (!empty($rows)) {
            $scores = array_slice($scores, 0, $rows);
        }
        return $this->response->setJSON(['result' => $scores]);
    }
}