import $ from 'jquery';
import * as $loadingTissues from "./common/loading-tissues";

$(function() {
    const prefId = null;
    const displayedChocoShopTableIds = window.displayedChocoShopTableIds;
    const displayedNightShopTableIds = window.displayedNightShopTableIds;

    $loadingTissues.init(
        $("meta[name='api-token-tissue']").attr('content'),
        "/api/v1/yc-championship/tissues/shop-ranking",
        {
            prefId: prefId,
            displayedChocoShopTableIds: displayedChocoShopTableIds,
            displayedNightShopTableIds: displayedNightShopTableIds
        },
        function(data, newChocoShopTableIds, newNightShopTableIds) {
            data.displayed_choco_shop_table_ids = newChocoShopTableIds;
            data.displayed_night_shop_table_ids = newNightShopTableIds;

            return data;
        }
    );
});
