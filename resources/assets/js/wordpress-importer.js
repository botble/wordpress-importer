class WordpressImporter {
    constructor() {
        this.categoryList = $('#category-select');
        this.categoryCheckbox = $('#copy_categories');
        this.listen();
    }

    listen() {
        $(document).on('click', '.import-wordpress-data', this.import.bind(this));

        this.categoryCheckbox.on('change', (e) => {
            this.toggleCategory(e.target.checked);
        });
    }

    toggleCategory(show = true) {
        if (show) {
            this.categoryList.slideUp();
        } else {
            this.categoryList.slideDown();
            setTimeout(this.loadCategory.bind(this), 500);
        }
    }

    loadCategory() {
        if (this.categoryList.hasClass('loaded')) {
            return;
        }

        this.categoryList.addClass('loaded');
        this.call({
            url: '/api/v1/categories'
        }).then(res => {
            const $ul = this.categoryList.find('ul');
            $ul.empty();
            if (!res.error && res.data.length) {
                res.data.forEach((item, index) => {
                    $ul.append(`<li class="${item.slug}">
                        <label for="${item.slug}" class="control-label">
                            <input ${index === 0 ? 'checked' : ''} type="radio" value="${item.id}" name="default_category_id" id="${item.slug}">
                            <span>${item.name}</span>
                        </label>
                    </li>`);
                });
            }
        });
    }

    import(event) {
        event.preventDefault();
        let _self = $(event.currentTarget);

        $('.wordpress-importer .alert').addClass('hidden');
        _self.addClass('button-loading');

        this.call({
            type: 'POST',
            url: _self.closest('form').prop('action'),
            data: new FormData(_self.closest('form')[0]),
        }).then(res => {
                if (!res.error) {
                    Botble.showSuccess(res.message);
                    $('.wordpress-importer .success-message').removeClass('hidden').text(res.message);
                } else {
                    Botble.showError(res.message);
                    $('.wordpress-importer .error-message').removeClass('hidden').text(res.message);
                }
                _self.removeClass('button-loading');
            },
            error => {
                Botble.handleError(error);
                _self.removeClass('button-loading');
            })
    }

    call(obj) {
        return new Promise((resolve, reject) => {
            $.ajax({
                type: 'GET',
                contentType: false,
                processData: false,
                ...obj,
                success(res) {
                    resolve(res);
                },
                error(res) {
                    reject(res);
                }
            })
        })
    }
}

$(document).ready(function () {
    new WordpressImporter();
});
