<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, viewport-fit=cover,  initial-scale=1">

    <title>第2回 夜遊びショコラ選手権！ナンバー1のキャバ嬢・キャストを決めよう！</title>
    <meta name="description" content="【夜遊びショコラ】×【体入ショコラ】コラボ企画『夜遊びショコラ選手権』を開催！全国から人気のキャバ嬢・キャストたちがナンバー1の座をかけて競います。あなたの一票が頂点を決める！推しのキャストや動画にイイネしてギフトカードをゲットしよう♪">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
</head>
<body>
<div>
    <div style="display: flex;align-items: center;gap: 30px;font-weight: bold;">
        <div style="background: #f99;border-radius: 50%;padding: 10px;color: white;font-size: 32px;width: 50px;height: 50px;text-align: center;"> {{ $shop->rank_num }}</div>
        <div style="display: flex;flex-direction: column;">
            <div>{{ $shop->choco_shop_table_id }} | {{ $shop->choco_shop_pref_id }} & {{ $shop->night_shop_table_id }} | {{ $shop->night_shop_pref_id }}</div>
            <div>Casts：{{ $shop->cast_ids }} </div><BR>
        </div>
    </div>
    @foreach($casts as $row)
        <div style="display: inline-flex;flex-direction: row;align-items: flex-start;flex-wrap: wrap;position: relative; margin: 0 0 50px;">
            <div style="width: 18vw;">
                <div style="height: 262px; width: 200px; position: relative;mask-image: radial-gradient(rgba(0, 0, 0, 0.3) 50%);">
                    <img
                        style="height: 100%; width: 100%; object-fit: cover;"
                        src="{{ $row->tissue->front_show_image_path }}"
                    >
                </div>
                <div style="position: absolute;left: 0;top: 0;font-weight: bold;">
                    <div>User: {{ $row->tissue->user_type }}</div>
                    <div>User ID: <span style="color: red;">{{ $row->tissue->user_id }}</span></div>
                    <div>Top Tissue ID: <span style="color: red;">{{ $row->tissue->id }}</span></div>
                    <div>Point: <span style="color: red;">{{ $row->rank_point }}</span></div>
                </div>
            </div>
        </div>
    @endforeach
</div>
</body>
</html>
