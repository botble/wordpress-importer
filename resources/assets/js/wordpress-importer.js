$(document).ready(function () {
    $(document).on('click', '.import-wordpress-data', event => {
        event.preventDefault();
        let _self = $(event.currentTarget);

        $('.wordpress-importer .alert').addClass('hidden');
        _self.addClass('button-loading');

        $.ajax({
            type: 'POST',
            url: _self.closest('form').prop('action'),
            data: new FormData(_self.closest('form')[0]),
            contentType: false,
            processData: false,
            success: res => {
                if (!res.error) {
                    Botble.showSuccess(res.message);
                    $('.wordpress-importer .success-message').removeClass('hidden').text(res.message);
                } else {
                    Botble.showError(res.message);
                    $('.wordpress-importer .error-message').removeClass('hidden').text(res.message);
                }
                _self.removeClass('button-loading');
            },
            error: res => {
                Botble.handleError(res);
                _self.removeClass('button-loading');
            }
        });
    });
});
