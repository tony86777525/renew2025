import $ from 'jquery';

export function init(
    apiToken,
    url,
    parameters,
    fn = {}
) {
    let loadTimes = 1;
    const data = {
        'load_times': loadTimes,
    };
    if (parameters.prefId !== null) {
        data.pref_id = parameters.prefId;
    }
    if (parameters.isPc !== null) {
        data.is_pc = parameters.isPc;
    }
    if (parameters.tissueIds !== null) {
        data.tissue_ids = parameters.tissueIds;
    }
    if (parameters.displayedChocoShopTableIds !== null) {
        data.displayed_choco_shop_table_ids = parameters.displayedChocoShopTableIds;
    }
    if (parameters.displayedNightShopTableIds !== null) {
        data.displayed_night_shop_table_ids = parameters.displayedNightShopTableIds;
    }

    loadingTissues(apiToken, url, data, fn);
}

function loadingTissues(apiToken, url, data, fn) {
    const button = $('[data-button="loadTissue"]');
    button.click(function () {
        let ajaxFlg = true;
        data.load_times += 1;

        const loaderTarget = $('.js-loader');

        let target = $('[data-target="tissues"]');
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': apiToken
            },
            url: url,
            type: 'post',
            data: data,
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

                    if (res.new_displayed_choco_shop_table_ids || res.new_displayed_night_shop_table_ids) {
                        data = fn(data, res.new_displayed_choco_shop_table_ids ?? null, res.new_displayed_night_shop_table_ids ?? null);
                        console.log(data);
                    }
                } else {
                    ajaxFlg = false;
                    // $('.f-pagination').show();
                }
            },
            error: function (res) {}
        });
    });
}
