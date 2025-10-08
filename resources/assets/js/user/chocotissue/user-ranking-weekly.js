import $ from 'jquery';
import * as $loadingTissues from "./common/loading-tissues";

$(function() {
    let prefId = null;

    $loadingTissues.init(
        $("meta[name='api-token-tissue']").attr('content'),
        "/api/v1/yc-championship/tissues/user-ranking-weekly",
        {
            prefId: prefId
        }
    );
});
