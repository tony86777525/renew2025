@foreach($data as $row)
    <div style="width: 18vw; position: relative;">
        <div style="height: 262px; max-width: 200px; mask-image: radial-gradient(rgba(0, 0, 0, 0.3) 50%);">
            <img
                style="height: 100%; width: 100%; object-fit: cover;"
                src="{{ $row->tissue->front_show_image_path }}"
            >
        </div>
        <div style="position: absolute;left: 0;top: 0;font-weight: bold;">
            <div>User: {{ $row->tissue->user_type }}</div>
            <div>User ID: <span style="color: red;">{{ $row->tissue->user_id }}</span></div>
            <div>LIKE Count: <span style="color: red;">{{ $row->tissue->good_count + $row->tissue->total_good_count }}</span></div>
            <div>SNS Count: <span style="color: red;">{{ $row->tissue->sns_count }}</span></div>
        </div>
    </div>
@endforeach
