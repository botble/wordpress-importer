class WordpressImporter {
    constructor() {
        this.categoryList = $('#category-select');
        this.categoryCheckbox = $('#copy_categories');
        this.listen();
    }

    listen() {
        $(document).on('submit', '.import-wordpress-form', this.import.bind(this));

        this.categoryCheckbox.on('change', (e) => {
            this.toggleCategory(e.target.checked);
        });

        $(document).on('click', '#category-select .btn-loadmore', e => {
            e.preventDefault();
            const $this = $(e.currentTarget);
            if ($this.attr('href')) {
                this.loadCategory($this.attr('href'));
            }
        });
    }

    toggleCategory(show = true) {
        if (show) {
            this.categoryList.slideUp();
        } else {
            this.categoryList.slideDown();
            if (! this.categoryList.hasClass('loaded')) {
                setTimeout(this.loadCategory.bind(this), 500);
            }
        }
    }

    loadCategory(url) {
        const $ul = this.categoryList.find('ul');
        if (this.categoryList.hasClass('loaded') && ! $ul.find('li').length) {
            return;
        }
        const $loadmore = this.categoryList.find('.btn-loadmore');

        this.categoryList.addClass('loaded');
        this
            .call({
                url: url || '/api/v1/categories',
                beforeSend: () => {
                    $loadmore.addClass('button-loading');
                },
                complete: () => {
                    $loadmore.removeClass('button-loading');
                },
            })
            .then(res => {
                if (!res.error && res.data.length) {
                    res.data.forEach((item, index) => {
                        $ul.append(`<li class="category-id-${item.id}">
                            <label for="category-id-${item.id}" class="control-label">
                                <input type="radio" value="${item.id}" name="default_category_id" id="category-id-${item.id}">
                                <span>${item.name}</span>
                            </label>
                        </li>`);
                    });

                    if (res.links?.next) {
                        $loadmore.attr('href', res.links.next).removeClass('d-none');
                    } else {
                        $loadmore.attr('href', '').addClass('d-none');
                    }
                }
            });
    }

    import(event) {
        event.preventDefault();
        let $form = $(event.currentTarget);

        $('.wordpress-importer .alert').addClass('hidden');
        
        const $button = $form.find('button[type=submit]');

        this
            .call({
                type: 'POST',
                url: $form.prop('action'),
                data: new FormData($form[0]),
                beforeSend: () => {
                    $button.addClass('button-loading');
                },
                complete: () => {
                    $button.removeClass('button-loading');
                }
            })
            .then(res => {
                if (! res.error) {
                    Botble.showSuccess(res.message);
                    $('.wordpress-importer .success-message').removeClass('hidden').text(res.message);
                } else {
                    Botble.showError(res.message);
                    $('.wordpress-importer .error-message').removeClass('hidden').text(res.message);
                }
            })
            .catch(error => {
                Botble.handleError(error);
            });
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
                },
            });
        })
    }
}

$(() => {
    new WordpressImporter();
});
