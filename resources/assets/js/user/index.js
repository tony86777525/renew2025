import $ from 'jquery';

$(function() {
    let loadTimes = 1;
    loadTissueData(loadTimes);
});

function loadTissueData(loadTimes) {
    const button = $('[data-button="loadTissue"]');
    button.click(function () {
        let ajaxFlg = true;
        let prefId = null;
        let isPc = true;
        loadTimes += 1;

        const loaderTarget = $('.js-loader');

        let target = $('[data-target="tissues"]');
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: '/api/v1/yc-championship/tissues/recommendations',
            type: 'post',
            data: {
                'api_token': $("meta[name='api-token-tissue']").attr('content'),
                'load_times': loadTimes,
                'pref_id': prefId,
                'is_pc': isPc
            },
            datatype: 'json',
            beforeSend: function () {
                ajaxFlg = false;
                loaderTarget.show();
            },
            complete: function () {
                loaderTarget.hide();
            },
            success: function (res) {
                if (res.have_next_load === false) {
                    button.hide();
                }
                if (res.html) {
                    ajaxFlg = true;
                    target.append(res.html);
                } else {
                    ajaxFlg = false;
                    // $('.f-pagination').show();
                }
            },
            error: function (res) {}
        });
    });
}
