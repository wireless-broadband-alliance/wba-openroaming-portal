$(document).ready(function () {
    setTimeout(function () {
        // Function to find and remove entire 201 response sections
        function remove201Responses() {
            $('table.responses-table').each(function () {
                $(this).find('td.response-col_status').each(function () {
                    if ($(this).text().trim() === '201') {
                        $(this).closest('tr').remove();
                    }
                });
            });

            // Find all response examples with 201 status
            $('[data-line]').each(function () {
                let responseCode = $(this).find('.response-col_status').text().trim();
                if (responseCode === '201') {
                    // Remove all related schema, media types, and example values under 201 responses
                    $(this).closest('[data-line]').remove();
                }
            });

            // Remove 201 response blocks
            $('.response').each(function () {
                if ($(this).find('.response-col_status').text().trim() === '201') {
                    $(this).closest('.response').remove();
                }
            });
        }

        // Suppress and ignore errors
        function suppressErrors() {
            $('.errors-wrapper').each(function () {
                const errorText = $(this).text();
                if (errorText.includes('Resolver error')) {
                    $(this).remove();
                }
            });
        }

        remove201Responses();
        suppressErrors();

    }, 1500);
});
