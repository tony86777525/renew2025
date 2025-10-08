@foreach($data as $row)
    <div style="display: flex;align-items: center;gap: 30px;">
        <div style="background: #f99;border-radius: 50%;padding: 10px;color: white;font-weight: bold;font-size: 32px;width: 50px;height: 50px;text-align: center;"> {{ $row->rank_num }}</div>
        <div style="display: flex;flex-direction: column;">
            <div>{{ $row->choco_shop_table_id }} | {{ $row->choco_shop_pref_id }} & {{ $row->night_shop_table_id }} | {{ $row->night_shop_pref_id }}</div>
            <div>Casts：{{ $row->cast_ids }} </div>
            <div style="background: #f99">Points：{{ $row->rank_point }}</div>
            <BR>
        </div>
    </div>
    @foreach($row->tissues as $tissue)
        <div style="display: inline-flex;flex-direction: row;align-items: flex-start;flex-wrap: wrap;position: relative; margin: 0 0 50px;">
            <div style="width: 18vw;">
                <div style="height: 262px; width: 200px; position: relative;mask-image: radial-gradient(rgba(0, 0, 0, 0.3) 50%);">
                    <img
                        style="height: 100%; width: 100%; object-fit: cover;"
                        src="{{ $tissue->front_show_image_path }}"
                    >
                </div>
                <div style="position: absolute;left: 0;top: 0;font-weight: bold;">
                    <div>User: {{ $tissue->user_type }}</div>
                    <div>User ID: <span style="color: red;">{{ $tissue->user_id }}</span></div>
                    <div>LIKE Count: <span style="color: red;">{{ $tissue->good_count + $tissue->total_good_count }}</span></div>
                    <div>SNS Count: <span style="color: red;">{{ $tissue->sns_count }}</span></div>
                </div>
            </div>
        </div>
    @endforeach
    <BR><BR>
@endforeach
