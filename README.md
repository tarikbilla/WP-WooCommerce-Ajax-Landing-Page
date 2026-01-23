# WP WooCommerce Ajax Landing Page

**Version:** 1.0 
**Author:** Tarik Billa

Auto-adds specific product to cart on page visit, handles variable attributes, and updates price via AJAX.

## Features

*   **Auto-Add to Cart:** Automatically clears the cart and adds a specific product when a user visits a specific landing page.
*   **Variable Product Support:** Fully supports variable products (Size, Color). You can set a default variation to load automatically.
*   **AJAX Price Update:** Changing product attributes or quantity updates the price and checkout totals instantly without reloading the page.
*   **Admin Dashboard:** Easy-to-use settings panel to select your landing page, search for products, and choose default variations.
*   **Shipping Recalculation:** Changing quantity or attributes automatically updates shipping costs in real-time.
*   **One-Product Mode:** Ensures only the selected product exists in the cart on the landing page.

## Installation

1.  Download the plugin zip file.
2.  Go to **WordPress Admin > Plugins > Add New**.
3.  Click **Upload Plugin** and select the zip file.
4.  Activate the plugin.
5.  Find the new menu **LP Checkout** in your admin sidebar to configure.

## Usage

### 1. Configure Settings
Go to **LP Checkout** in your WordPress dashboard:

1.  **Select Landing Page:** Choose the page where you want the checkout form to appear.
2.  **Search Product:** Type the name of the product you want to add automatically. Click a result to select it.
3.  **Default Variation:** If your product has variations (e.g., Size/Color), select the specific combination you want loaded by default when a user visits the page.

### 2. Add Shortcode
Copy the shortcode `[lp_checkout_form]` from the settings page.

Edit your Landing Page and paste the shortcode in the content area where you want the product attributes and checkout button to appear.

### 3. How it works
*   When a user visits your landing page, the plugin detects it.
*   It clears any existing products in the cart.
*   It adds your configured product (or default variation) to the cart.
*   The product attributes (Size/Color) are displayed on the page.
*   When the user changes a quantity or selects a new color/size, the price and shipping update instantly via AJAX.
*   The user proceeds to checkout directly on that page.
