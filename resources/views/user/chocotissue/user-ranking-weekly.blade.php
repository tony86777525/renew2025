<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, viewport-fit=cover,  initial-scale=1">

    <title>第2回 夜遊びショコラ選手権！ナンバー1のキャバ嬢・キャストを決めよう！</title>
    <meta name="description" content="【夜遊びショコラ】×【体入ショコラ】コラボ企画『夜遊びショコラ選手権』を開催！全国から人気のキャバ嬢・キャストたちがナンバー1の座をかけて競います。あなたの一票が頂点を決める！推しのキャストや動画にイイネしてギフトカードをゲットしよう♪">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    @vite(['resources/assets/js/user/chocotissue/user-ranking-weekly.js', 'resources/assets/sass/user/chocotissue/index.scss'])
</head>
<body>
<div>
    <div style="display: inline-flex;flex-direction: row;align-items: flex-start;flex-wrap: wrap;position: relative; margin: 0 0 50px;" data-target="tissues">
        @include('user.chocotissue.common.user-ranking-weekly-tissues')
    </div>
</div>

@include('user.chocotissue.common.footer-container')
</body>
</html>
