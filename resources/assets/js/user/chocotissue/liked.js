import $ from 'jquery';
import * as $loadingTissues from "./common/loading-tissues";

$(function() {
    let prefId = null;
    let tissueIds = window.tissueIds;

    $loadingTissues.init(
        $("meta[name='api-token-tissue']").attr('content'),
        "/api/v1/yc-championship/tissues/liked",
        {
            prefId: prefId,
            tissueIds: tissueIds
        },
    );
});
