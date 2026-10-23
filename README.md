# Overview

This is a plugin for Botble CMS, so you have to purchase Botble CMS first to use this plugin.
Purchase it
here: [https://codecanyon.net/item/botble-cms-php-platform-based-on-laravel-framework/16928182](https://1.envato.market/LWRBY)

![Sreenshot](./art/screenshot-1.png)

# Installation

## Install via Admin Panel

Go to the **Admin Panel** and click on the **Plugins** tab. Click on the "Add new" button, find the **WordPress Importer**
plugin and click on the "Install" button.

## Install manually

1. Download the plugin from
   the [Botble Marketplace](https://marketplace.botble.com/products/botble/wordpress-importer).
2. Extract the downloaded file and upload the extracted folder to the `platform/plugins` directory.
3. Go to **Admin** > **Plugins** and click on the **Activate** button.

## Usage

### Import Base Data

This option imports essential data such as pages, posts, tags, categories, and users.

1. To obtain the XML file for importing data, go to your **WordPress admin panel**.
2. Navigate to **Tools** > **Export**.
3. Select All content and click the **Download Export File** button.
    ![Screenshot](./art/screenshot-3.png)
4. Once you've downloaded the XML file, go to your **Botble admin panel**.
5. Navigate to **Tools** > **WordPress Importer**.
6. Click on the **WordPress XML file** field to choose the XML file you just downloaded.
7. Click the **Import** button.
![Screenshot](./art/screenshot-2.png)

### Import WooCommerce Products

To import WooCommerce products to your Botble ecommerce site:

1. Ensure your script supports Ecommerce features, including having an **Ecommerce** plugin and compatible themes.
2. Export the products CSV file from your **WordPress admin dashboard**.
   - Go to **Products** > **All Products**.
   - Click on the **Export** button in the top left corner.
   - Then click **Generate CSV**.
    ![Screenshot](./art/screenshot-4.png)
4. After exporting the CSV file, go to your **Botble admin panel**.
5. Navigate to **Tools** > **WordPress Importer**.
6. Click on the upload area to select the CSV file you just downloaded.
7. Click the **Import** button.

![Screenshot](./art/screenshot-5.png)

### Images

Featured images, images inside post/page/product content (including `srcset`, lazy-load `data-src`, links to full-size files and CSS backgrounds) and WooCommerce product gallery images are downloaded into your media library and the content is updated to point at the local copies.

If an image can't be downloaded, the original URL is kept and the reason is listed in the import results. A common cause is **Cloudflare bot protection** on the WordPress site ("Blocked by Cloudflare bot protection"): allow your Botble server's IP in Cloudflare (**Security** > **WAF** > **Tools**) or pause protection while importing, then run the import again. Posts, pages and products that were already imported aren't duplicated, but any of their images still pointing at the old site are downloaded and fixed.

For safety, image URLs that resolve to private network addresses are refused. To import from a WordPress site on your own machine or LAN (e.g. `http://wordpress.test`), set `WP_IMPORTER_ALLOW_PRIVATE_IMAGE_HOSTS=true` in `.env`.

# Contact us

- Website: [https://botble.com](https://botble.com)
- Email: [contact@botble.com](mailto:contact@botble.com)
