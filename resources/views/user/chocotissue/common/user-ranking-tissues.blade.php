@foreach($data as $row)
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
                <div>Point: <span style="color: red;">{{ $row->point }}</span></div>
                <div>LIKE Count: <span style="color: red;">{{ $row->total_good_count }}</span></div>
                <div>SNS Count: <span style="color: red;">{{ $row->total_sns_count }}</span></div>
                <div>COMMENT Count: <span style="color: red;">{{ $row->total_comment_count }}</span></div>
            </div>
        </div>
    </div>
@endforeach
