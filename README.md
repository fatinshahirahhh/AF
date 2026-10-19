# AF CULTURED clothing boutique

An editorial clothing storefront with a PHP seller studio. The shop is named AF CULTURED. The collection contains the seller-provided black and cream Growing in Silence T-shirts at RM79. Seller contacts are configured: WhatsApp +60102356513, TikTok @af_cultured, and Instagram @af_cultured.my.

## Open locally

Start Apache in XAMPP and visit http://localhost/AF/dist/ . Open http://localhost/AF/dist/editor.html to create the first seller account, then log in. The first account setup must happen before exposing the site publicly. Use a unique password of at least 12 characters.

You can also open http://localhost/AF/ . First-account creation is restricted to localhost; no default seller password is supplied. PHP needs the PDO SQLite extension (included in this XAMPP installation). Shop data and password hashes are stored in `storage/shop.sqlite`, seller sessions in `storage/sessions/`, and photos in `dist/uploads/`. Apache blocks web access to `storage/` using its `.htaccess` file. Keep the complete AF folder structure when copying to PHP hosting, and back up the database and uploads together. If you forget the seller password, contact the site administrator; this version has no email password reset.

The seller can add, edit and delete products, upload JPG/PNG/WebP photos (maximum 2 MB each), and configure the shop name and contacts. Choose **Save changes to website** to publish the catalogue for all visitors. Customers select a size and colour and send an enquiry on WhatsApp; stock, payment and delivery are agreed with the seller. There is no online payment or cart.

Each product also has an editable total stock quantity. Blank means availability must be confirmed, a positive whole number displays the remaining quantity, and zero displays Sold out and disables product ordering. Stock is a total across all sizes, not a count per size. Update quantities manually after confirming WhatsApp orders; sending an enquiry does not reserve or deduct stock. The cream design has 5 remaining and black has 12, as confirmed by the seller. Both designs are RM79 with sizes S, M, L and XL.

The **Homepage** section in the seller studio manages the announcement, banner headline/description/button/photo, brand story text/photo, collection heading, featured products and their order, and photoshoot gallery (up to 12 photos). Upload new JPG/PNG/WebP photos under 2 MB, enter a photo description, and choose **Save changes to website**. Uploading alone does not publish the image. Gallery photos can be replaced, reordered, or removed; removing all photos hides the gallery. Leaving all featured products unchecked shows the complete collection. Homepage settings share the same database, authentication, and catalogue backup as products; older catalogues without homepage settings use the existing campaign as defaults.

## Hosting

The functional site needs PHP hosting with writable storage for the backend's catalogue, account data and uploaded photos. Keep server-side account/data directories inaccessible to web requests and use HTTPS on a public domain. The backend supplies protections in its server configuration. Preserve these protections when changing web servers. Back up persistent server files and uploads; do not replace those when updating design files.

A static host displays the seeded `catalogue.json` as a design preview. It cannot run seller login, uploads or shared saving; the editor explains that the PHP server is unavailable and does not pretend to save changes. The storefront requests `api.php?action=catalogue` first and falls back to the sample JSON only if the API is unavailable.

## Backups and sample content

Download a catalogue backup from the seller studio. Importing a backup updates the editor only until **Save changes to website** is selected. Uploaded images are separate files and must be backed up with the server uploads folder. `catalogue.json` is the static preview seed; it is not the live API catalogue after saving.

The AI-generated editorial campaign is `dist/assets/editorial.jpg` (built-in image generation, prompt: luxury independent womenswear campaign, adult woman in cream linen blouse and taupe trousers in limestone archway, second panel linen detail, soft sunlight, neutral palette, no text). Garment SVG illustrations are sample artwork, not photographs of stock. Replace them with accurate product photos before selling. Google Fonts provides Cormorant Garamond and Manrope; local serif/sans fallbacks are included.

The hero, story and campaign gallery now use the seller-provided photoshoot images copied into dist/assets/campaign-crew.jpg, campaign-front-back.jpg and campaign-street.jpg. Original product front/back images remain in the catalogue.

Product editing includes separate Front, Back and Both views photo slots with previews. Front is required; the other two are optional and can be replaced or removed independently. Each photo supports JPG/PNG/WebP up to 2 MB. Choose Keep changes, then Save changes to website. Customers see correctly named photo tabs even when an optional view is absent.

See DEPLOYMENT.md for the latest launch checks, hosting requirements and remaining steps. Create a protected website/database/photo backup using scripts/backup.php.
