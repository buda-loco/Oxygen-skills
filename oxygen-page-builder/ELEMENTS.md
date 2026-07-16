# Oxygen 6.1 element inventory (165 native elements)

**The point of this file: use native elements instead of `PhpCode`/`HtmlCode` wherever one exists.**

## Get any element's exact shape (never guess/harvest again)
`\Breakdance\Elements\get_elements_for_builder()` returns ALL registered elements. Each entry has:
`slug` (the tree `data.type` string), `name` (builder label), `category`, `htmlTag`, `controls`, and — crucially — **`defaultProperties` + `defaultChildren`**, which are the exact io-ts-valid property/child shapes the builder uses when you insert the element. To build any element correctly:
```php
$e = current(array_filter(\Breakdance\Elements\get_elements_for_builder(), fn($x)=>((array)$x)['slug']==='EssentialElements\\AdvancedTabs'));
$e=(array)$e; $props=$e['defaultProperties']; $kids=$e['defaultChildren']; // golden, builder-valid
```
`defaultChildren` gives the full nested tree for composites (Tabs→TabLink/TabContent, Accordion, Slider, Product Builder, Menu Builder). Copy it, swap the content, inject. No harvest needed.

Below: `▸` = composite (has `defaultChildren`). `slug` is what goes in `node.data.type`.

## Basic / layout
- Section `EssentialElements\Section` · Container `OxygenElements\Container` · Div `EssentialElements\Div` · Columns `EssentialElements\Columns` + Column `EssentialElements\Column` · Grid `EssentialElements\Grid` · Fancy Container `EssentialElements\FancyContainer`
- Container Link `OxygenElements\ContainerLink` (wraps `%%CHILDREN%%` in `<a>`; **use this** to wrap any children in a link) · ⚠ Wrapper Link `EssentialElements\WrapperLink` — **AVOID for injection**: its `defaultProperties` advertise `content.content.url`, but that key does NOT render an href (outputs `href="#"`) — a render-keys mismatch (GOTCHAS §wrapper-link-href). ContainerLink reads `content.content.url` correctly.

## Content
- Text `OxygenElements\Text` (tag via `settings.advanced.tag`) · Rich Text `OxygenElements\RichText` · Heading `EssentialElements\Heading` · Dual/Animated Heading `EssentialElements\{DualHeading,AnimatedHeading}` · Blockquote `EssentialElements\Blockquote`
- Text Link `OxygenElements\TextLink` · Button `EssentialElements\Button` · Badge `EssentialElements\Badge`
- Basic/Icon/Checkmark List `EssentialElements\{BasicList,IconList,CheckmarkList}` · Icon `EssentialElements\Icon` · Icon Box `EssentialElements\IconBox` · Table Of Contents `EssentialElements\TableOfContents`

## Media
- Image `OxygenElements\Image` (or `EssentialElements\Image2`) · HTML IMG `EssentialElements\HtmlImg` · Image Box/Hover/Zoom/Comparison/Accordion `EssentialElements\{ImageBox,ImageHoverCard,ImageWithZoom,ImageComparison,ImageAccordion}`
- Gallery `EssentialElements\Gallery` (binds to a Gallery data point) · Video `EssentialElements\Video` · HTML5 Video `OxygenElements\Html5Video` · Lottie `EssentialElements\LottieAnimation` · SVG Icon `OxygenElements\SvgIcon` · Icon `EssentialElements\Icon`

## Interactive (▸ = native, editable — prefer over custom JS)
- ▸ Tabs `EssentialElements\Tabs` · ▸ Advanced Tabs `EssentialElements\AdvancedTabs` (children ▸`TabLink` = labels, ▸`TabContent` = panels)
- ▸ Advanced Accordion `EssentialElements\AdvancedAccordion` (+ `AccordionContent`) · Frequently Asked Questions `EssentialElements\FrequentlyAskedQuestions`
- ▸ Advanced Slider `EssentialElements\Advancedslider` (+ ▸`Advancedslide`) · Basic Slider `EssentialElements\Basicslider`
- ▸ Content Toggle `EssentialElements\ContentToggle` (+ `ContentToggleContent`) · ▸ Content Reveal `EssentialElements\ContentReveal` · Tooltip `EssentialElements\Tooltip` · Popup `EssentialElements\Popup` · Countdown Timer `EssentialElements\CountdownTimer` · Back To Top `EssentialElements\BackToTop`

## WooCommerce (full set — use these, not PHP)
- **Single product**: Product `EssentialElements\Product` (whole product in one block) · ▸ **Product Builder** `EssentialElements\Productbuilder` (compose a custom single-product layout from the parts below) · Product Images `EssentialElements\Wooproductimages` (gallery+thumbs) · Product Title `EssentialElements\WooProductTitle` · Product Price `EssentialElements\Wooproductprice` · Product Cart Button `EssentialElements\Wooproductcartbutton` (variation add-to-cart) · Product Tabs `EssentialElements\Wooproducttabs` · Product Description/Excerpt/Info/Meta/Stock/Rating/Reviews `EssentialElements\{ProductDescription,ProductExcerpt,Wooproductinfo,Wooproductmeta,Wooproductstock,Wooproductrating,ProductReviews}` · Woo Breadcrumb `EssentialElements\WooBreadcrumb`
- **Archive/shop**: Products List `EssentialElements\Wooproductslist` · Shop Page `EssentialElements\Wooshoppage` · Shop Filters `EssentialElements\WooShopFilters` · Related Products `EssentialElements\RelatedProducts` · Upsell Products `EssentialElements\UpsellProducts`
- **Cart**: Cart Page `EssentialElements\Woopageshoppingcart` · Cart Contents/Totals/CrossSells/EmptyMessage `EssentialElements\{WooCartContents,WooCartTotals,WooCartCrossSells,WooCartEmptyMessage}` · Mini Cart `EssentialElements\MiniCart`
- **Checkout**: ▸ Checkout Builder `EssentialElements\CheckoutBuilder` · Checkout Page `EssentialElements\Woopagecheckout` · Checkout Billing/Shipping/Login/Coupon/Payment/OrderReview forms `EssentialElements\WooCheckout{BillingForm,ShippingForm,LoginForm,CouponForm,Payment,OrderReview}`
- **Account/orders**: Account Page `EssentialElements\Woopageaccount` · Order Tracking Page `EssentialElements\Woopageordertracking`

## Dynamic / post (category `dynamic`)
- Post Loop Builder `OxygenElements\PostsLoop` · Repeater Field `OxygenElements\DynamicDataLoop` · Term Loop Builder `OxygenElements\TermLoopBuilder` · Template Content Area `OxygenElements\TemplateContentArea` · Widget `OxygenElements\WpWidget`
- Post fields: Post Title/Content/Excerpt/Meta `EssentialElements\{PostTitle,PostContent,PostExcerpt,PostMeta}` · Archive Title `EssentialElements\ArchiveTitle` · Author `EssentialElements\Author` · Post List `EssentialElements\Postslist` · Breadcrumbs `EssentialElements\Breadcrumbs` · Adjacent Posts `EssentialElements\AdjacentPosts` · Comments `EssentialElements\{CommentForm,CommentsList}`

## Menu / header (for header/footer templates)
- ▸ Menu Builder `EssentialElements\MenuBuilder` (renders a WP menu natively — use instead of PhpCode nav!) · WP Menu `EssentialElements\WpMenu` · Menu Link/Button/Dropdown/CustomDropdown/CustomArea `EssentialElements\{MenuLink,MenuButton,MenuDropdown,MenuCustomDropdown,MenuCustomArea}` · ▸ Header Builder `EssentialElements\HeaderBuilder` · Search Form `EssentialElements\SearchForm` · Notification Bar `EssentialElements\NotificationBar` · Social Icons `EssentialElements\SocialIcons`

## Forms (category `breakdance-forms-for-oxygen`)
- ▸ Form Builder `EssentialElements\FormBuilder` · Login Form `EssentialElements\LoginForm` · Register Form `EssentialElements\RegisterForm`

## Widgets / stats / social
- Pricing Table `EssentialElements\PricingTable` · Stats Grid `EssentialElements\StatsGrid` · Simple/Circle Counter `EssentialElements\{SimpleCounter,CircleCounter}` · Progress/Star Rating `EssentialElements\{ProgressBar,StarRating}` · Testimonials `EssentialElements\{SimpleTestimonial,FancyTestimonial}` · Logo List `EssentialElements\LogoList` · Business Hours `EssentialElements\BusinessHours` · Social Share `EssentialElements\SocialShareButtons` · Google Map `EssentialElements\GoogleMap` · Facebook/Twitter/Instagram embeds `EssentialElements\{FacebookPost,TwitterEmbedTweet,InstagramPost,…}`

## Code / advanced (escape hatches — use only when no native element fits)
- PHP Code `OxygenElements\PhpCode` · HTML Code `OxygenElements\HtmlCode` · CSS Code `OxygenElements\CssCode` · JavaScript Code `OxygenElements\JavaScriptCode` · Code Block `EssentialElements\CodeBlock` · Shortcode/Container Shortcode `OxygenElements\{Shortcode,ContainerShortcode}` · oEmbed `OxygenElements\oEmbed` · **Component `OxygenElements\Component`** = insert a reusable "Component"/Global Block (post type `oxygen_block`) via `content.content.block.componentId` — see RECIPES §Reusable Components · Global Block `EssentialElements\Globalblock`

## Element source files (render-key ground truth)
`defaultProperties` can lie about the render key — the element's **`html.twig` is the truth**
(e.g. FAQ renders `content.settings.items`, not `.questions`). Sources live under FIVE roots;
**dir names are Underscored_Label_Words, not the slug** (`Frequently_Asked_Questions/`, not
`FrequentlyAskedQuestions/`):
- `plugins/oxygen/subplugins/breakdance-elements/elements/` — EssentialElements
- `plugins/oxygen/subplugins/oxygen-elements/elements/` — OxygenElements
- `plugins/oxygen/plugin/elements/` — registration APIs (`get_elements_for_builder` lives here)
- `plugins/breakdance-elements-for-oxygen/elements/` — add-on elements (FAQ, Product, …)
- `plugins/breakdance-forms-for-oxygen/elements/` — form elements
Each element dir: `{element.php, html.twig, default.css, css.twig}`.

## Takeaways for this project
- The PDP/PLP/cart/checkout can ALL be native WC elements (or their Builder composites) — no PHP needed.
- The header/footer nav should be **Menu Builder**, not a PhpCode nav.
- Custom spec tabs → **Advanced Tabs** (`AdvancedTabs`); pull its `defaultChildren` for the exact shape.
- For ANY element, fetch `defaultProperties`/`defaultChildren` via `get_elements_for_builder()` before building.
