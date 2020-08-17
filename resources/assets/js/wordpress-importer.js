class WordpressImporter {
    constructor() {
        this.categoryList = $('#category-select');
        this.categoryCheckbox = $('#copy_categories');
        this.API_URL = `${window.location.href.split('admin')[0]}api/v1`
        this.listen();
    }

    listen() {
        $(document).on('click', '.import-wordpress-data', this.import.bind(this));

        this.categoryCheckbox.on('change', (e) => {
            this.toggleCategory(e.target.checked)
        })
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
        // if loaded
        if (this.categoryList.hasClass('loaded')) return;

        this.categoryList.addClass('loaded');
        this.call({
            url: this.API_URL + '/categories'
        }).then(res => {
            const $ul = this.categoryList.find('ul');
            const createNewObj = {
                slug: 'create-new',
                name: 'I wanna create new category...'
            }
            $ul.empty();
            if (!res.error && res.data.length) {
                console.log(res.data);
                [...res.data, createNewObj].forEach((item, index) => {
                    $ul.append(`<li class="${item.slug === 'create-new' ? 'text-danger' : ''}">
                        <label for="${item.slug}" class="control-label">
                            <input ${index === 0 ? 'checked' : ''} type="radio" value="${encodeURIComponent(JSON.stringify(item))}" name="category_default" id="${item.slug}">
                            <span>${item.name}</span>
                        </label>
                    </li>`)

                    $ul.find('input[type=radio]').on('change', (e) => {
                        if (e.target.id === 'create-new' && e.target.checked) {
                            let $target = $(e.target);
                            if (!this.$input) {
                                this.$input = $(`<input type="text" class="form-control" name="new_category" placeholder="Please input category name" />`);
                                $target.parents('li').append(this.$input);

                                this.$input.focus();
                                this.$input.on('keyup', e => {
                                    createNewObj.name = e.target.value || 'I wanna create new category...';
                                    $target.attr('value', encodeURIComponent(JSON.stringify(createNewObj)))
                                        .next('span').text(createNewObj.name)
                                })
                            } else {
                                this.$input.show();
                                this.$input.focus();
                            }

                        } else {
                            this.$input.hide();
                        }
                    })
                })
            }
        })
    }

    import(event) {
        event.preventDefault();
        let _self = $(event.currentTarget);

        if (this.imported) {
            window.location.reload();
        }

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
                _self.text('Refresh page');
                this.imported = true;
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
