# 職務経歴書解析の実施手順

## 環境構築

### DockerDesktopのインストール

https://www.docker.com/products/docker-desktop  
`docker-compose`ファイルを使って環境構築しているため、  
`docker-compose -v`でコマンドが実行できるかどうかも確認しておく。

また、システム上大量のメモリ（最低2G）が必要になるため、  
Settings > Resources > ADVANCED からメモリの量を変更しておいてください。

### Dockerでコンテナを立ち上げる

`docker-compose.yml`ファイルがあるディレクトリをカレントディレクトリにして、  
`docker-compose up -d`を実行する。  
失敗した場合は以下のサイトを参考に設定等を見直して再度実行してください。  
https://qiita.com/ksh-fthr/items/6b1242c010fac7395a45#%E3%83%8F%E3%83%9E%E3%82%8A%E3%81%A9%E3%81%93%E3%82%8D
  
コンテナの起動後、webコンテナのCLIを起動する。  
起動方法は、DockerDesktopのUIから起動するもよし、コマンドで起動するもよし。  
CLI画面が起動後、`/var/www/html`に移動して（恐らくデフォルトでこのディレクトリ）  
`composer install`を実行する。  
インストールが完了すれば準備完了です。  
`php spark`を実行し、何かしら色々表示すれば問題ありません。
```
# php spark


CodeIgniter CLI Tool - Version 4.0.4 - Server-Time: 2020-10-26 03:32:17am


Cache
  cache:clear        Clears the current system caches.

CodeIgniter
  help               Displays basic usage information.
  list               Lists the available commands.
  namespaces         Verifies your namespaces are setup correctly.
  routes             Displays all of user-defined routes. Does NOT display auto-detected routes.
  serve              Launches the CodeIgniter PHP-Development Server.
  session:migration  Generates the migration file for database sessions.

CurriculumVitae
  dictionary:create  WikipediaのダンプからIDF辞書を作成します。
  dictionary:merge   分割作成した辞書ファイルをマージします。
  score              BM25でxlsx形式の文書内の単語の重要度を算出します。
                     対象のファイルは writable/uploads/vitae 以下のものとなる。

Database
  db:seed            Runs the specified seeder to populate known data into the database.
  migrate            Locates and runs all new migrations against the database.
  migrate:create     Creates a new migration file.
  migrate:refresh    Does a rollback followed by a latest to refresh the current state of the database.
  migrate:rollback   Runs the "down" method for all migrations in the last batch.
  migrate:status     Displays a list of all migrations and whether they've been run or not.

Generators
  make:seeder        Creates a new seeder file.
```

#### `composer install`中にトークンを聞かれた場合

下記サイトを参考にして、githubでトークンを発行し、入力する。  
https://qiita.com/n0bisuke/items/54169b5e3800f792b8df

### 辞書ファイルの準備

このスコア計算のシステムに使用している辞書ファイルは独自に作成したものです。  
githubのファイル容量制限に引っかかったので、別のフォルダに置きました。  
<!-- https://drive.google.com/drive/folders/1j5sUj5zNXeKYM6Z6n-5sWE0jAP-x7Bke?usp=sharing -->
辞書ファイルは`src/writable/uploads/vitae`以下へ配置してください。

#### 辞書ファイルを作り直す場合

以下のURLからWikipediaのダンプファイルをダウンロードします。  
https://dumps.wikimedia.org/jawiki/  
その後、`src/writable/uploads`に解凍したダンプファイルを置き辞書作成のコマンドを実行します。
```
# php spark dictionary:create
```
ダンプファイルの名前を変更していない限りは自動で辞書を作ります。  
が、作成にはかなり時間がかかります。（12時間ぐらい？）  

また、現在の辞書ファイルは`20201001`のダンプファイルで作成しています。

## 解析を行う対象ファイルの準備

解析を行う対象のファイルは基本的にExcelの職務経歴書です。  
以下の3点の確認を行ってください。

- ファイルにパスワードがかかっている場合はテキストの抽出を行えないため、パスワードを解除しておきましょう。
- 全てのシートのテキストを抽出します。サンプルのシートが残っているとノイズとなるため、サンプルは削除してください。
- 対象はExcel2007以降の形式のみです。拡張子が`xls`の場合は必ず`xlsx`への変換を行ってください。

確認完了後、対象のファイルを`writable/uploads/vitae`へ格納してください。  
コマンドを実行する都合上、ファイル名に全角は含めない方がいいので、  
このタイミングでリネームしておきましょう。（全角があっても問題はありません）

また、対象を職務経歴書と言ってますが、ぶっちゃけExcelファイルなら何でもいいです。  
履歴書でも、仕様書でも、議事録でも、セルにテキストが記述されてるExcelなら。

# 解析の実施

まずはスコア計算を行うコマンドのヘルプを確認します。  
`php spark help score`を実行してください。
```
# php spark help score


CodeIgniter CLI Tool - Version 4.0.4 - Server-Time: 2020-10-26 02:16:32am

Description:
   BM25でxlsx形式の文書内の単語の重要度を算出します。
対象のファイルは writable/uploads/vitae 以下のものとなる。

Usage:
   score TargetFileName [-k 2.0] [-b 0.75] [-l 20]

Options:
   -k      TF-IDF値の影響の大きさを調整するパラメータ。1.2もしくは2.0を設定する。設定しない場合は2.0となる。
   -b      文書の単語数による影響の大きさを調整するパラメータ。0.0から1.0の間で設定する。設定しない場合は0.75となる。
   -l      一覧表示する件数。設定しない場合は全件表示する。
```

最短のコマンドで実行する場合は、以下の通りです。（対象ファイルは例として`a-seki.xlsx`とします。）
```
# php spark score a-seki.xlsx
```
ただし、このままだと全ての単語のスコアが表示されるため、結果が見にくくなります。  
なので、基本的には`l`オプションを付けて実行することをオススメします。  
```
# php spark score a-seki.xlsx -l 20
```

その他のオプションの`k`と`b`は基本的に設定せずに実行するかと思います。  
このオプションを指定することによる変化は、以下のサイトを見て確認してください。  
https://mieruca-ai.com/ai/tf-idf_okapi-bm25/#toc_2

## 解析結果の詳細

以下の解析結果から詳細を確認します。
```
# php spark score a-seki.xlsx -l 20


CodeIgniter CLI Tool - Version 4.0.4 - Server-Time: 2020-10-26 02:16:46am

実行時間 : 12.582528829575
  設計 : 0.007196 (0.027166)
  PHP : 0.006598 (0.014684)
  実装 : 0.005618 (0.015419)
  WinSCP : 0.005326 (0.008076)
  JavaScript : 0.005069 (0.011013)
  作成 : 0.005007 (0.022761)
  機能 : 0.004811 (0.018355)
  開発 : 0.004575 (0.01909)
  API : 0.004131 (0.009545)
  Git : 0.004031 (0.007342)
  カスタマイズ : 0.003692 (0.008811)
  Windows : 0.003436 (0.009545)
  PostgreSQL : 0.003194 (0.005874)
  管理 : 0.003182 (0.012482)
  MySQL : 0.0031 (0.005874)
  Navicat : 0.003034 (0.004405)
  サイト : 0.003032 (0.011747)
  jQuery : 0.002988 (0.00514)
  Laravel : 0.002973 (0.004405)
  SourceTree : 0.002915 (0.003671)
```

こちらは実行したコマンド。
```
# php spark score a-seki.xlsx -l 20
```

こちらは`spark`コマンドを実行した場合に必ず表示されるため無視します。
```
CodeIgniter CLI Tool - Version 4.0.4 - Server-Time: 2020-10-26 02:16:46am
```

以下が対象ファイルの解析結果です。
```
実行時間 : 12.582528829575
  設計 : 0.007196 (0.027166)
  PHP : 0.006598 (0.014684)
  実装 : 0.005618 (0.015419)
  WinSCP : 0.005326 (0.008076)
  JavaScript : 0.005069 (0.011013)
...
```

まず、1行目は実行時間です。  
秒単位で表示しています。
```
実行時間 : 12.582528829575
```

以降の行が各単語とスコアです。
内訳は以下です。
```
  設計 : 0.007196 (0.027166)

  対象の単語 : BM25のスコア (TF値：文書内の単語の出現頻度)
```

また、全件表示した場合に、BM25のスコアが0のものがありますが、  
これは、スコア算出時に使用している辞書の中から単語が見つけられなかったものです。  
辞書引きでは大文字・小文字・全角・半角、全て区別しています。  
スコアが0のものは、誤字やスペース区切りのミスなどでないか確認してください。