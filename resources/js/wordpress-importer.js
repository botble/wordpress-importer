$(() => {
    const $categoryList = $('#category-select')

    const loadCategory = () => {
        const $slot = $categoryList.find('[data-bb-toggle="slot-categories"]')

        if ($categoryList.hasClass('loaded') && !$slot.find('label').length) {
            return
        }

        const $loadMoreButton = $categoryList.find('[data-bb-toggle="load-more"]')

        $categoryList.addClass('loaded')

        $httpClient
            .make()
            .withButtonLoading($loadMoreButton)
            .post($loadMoreButton.data('url'))
            .then(({ data }) => {
                if (!data.error && data.data.data.length) {
                    data.data.data.forEach((item) => {
                        // Build the label/input/span via DOM nodes so the
                        // server-supplied category name and id never reach
                        // innerHTML. Mitigates stored XSS via a poisoned
                        // Category record.
                        const label = document.createElement('label')
                        label.className = 'form-check'

                        const input = document.createElement('input')
                        input.className = 'form-check-input'
                        input.type = 'radio'
                        input.name = 'default_category_id'
                        input.value = String(item.id)

                        const span = document.createElement('span')
                        span.className = 'form-check-label'
                        span.textContent = String(item.name)

                        label.appendChild(input)
                        label.appendChild(span)
                        $slot[0].appendChild(label)
                    })

                    if (data.data.next_page_url) {
                        $loadMoreButton.data('url', data.data.next_page_url).show()
                    } else {
                        $loadMoreButton.data('url', '').hide()
                    }
                }
            })
    }

    $(document)
        .on('submit', '.import-wordpress-form', (e) => {
            e.preventDefault()

            const $form = $(e.currentTarget)

            $('.wordpress-importer .result-message').hide()

            $httpClient
                .make()
                .withButtonLoading($form.closest('.card').find('button[type=submit]'))
                .post($form.prop('action'), new FormData($form[0]))
                .then(({ data }) => {
                    const $result = $('.wordpress-importer .result-message').show().text(data.message)

                    if (!data.error) {
                        $result.removeClass('alert-danger').addClass('alert-success')
                        $form[0].reset()
                    } else {
                        $result.removeClass('alert-success').addClass('alert-danger')
                    }
                })
        })
        .on('click', '#category-select [data-bb-toggle="load-more"]', (e) => {
            e.preventDefault()

            loadCategory()
        })
        .on('change', '#copy_categories', (e) => {
            if (e.target.checked) {
                $categoryList.slideUp()
            } else {
                $categoryList.slideDown()
                if (!$categoryList.hasClass('loaded')) {
                    loadCategory()
                }
            }
        })
})
