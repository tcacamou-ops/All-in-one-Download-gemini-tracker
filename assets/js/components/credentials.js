jQuery(document).ready(function ($) {
    const $document = $(document); // Cache document lookup

    // init events listeners
    $document.on('click', '#submit-gemini-tracker-credentials', submit_gemini_tracker_credentials);

    function submit_gemini_tracker_credentials(e) {
        e.preventDefault();
        allI1d.requestWPApi(
            allI1d_gemini_tracker.api.routes.credentials,
            {
                gemini_tracker_api_key: $('#gemini_tracker_api_key').val(),
            },
            function (response, data) {
                allI1d.showToast('Saved', 'success');
                setTimeout(function () { location.reload(); }, 1000);
            },
            'POST',
            function (request, error) {
                var message = (request.responseJSON && request.responseJSON.message)
                    ? request.responseJSON.message
                    : error;
                allI1d.showToast(message, 'error');
            }
        );
    }
});
