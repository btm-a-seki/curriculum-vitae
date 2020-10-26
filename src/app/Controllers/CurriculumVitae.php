<?php namespace App\Controllers;

// TODO: API作成の準備
class CurriculumVitae extends BaseController
{
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
}