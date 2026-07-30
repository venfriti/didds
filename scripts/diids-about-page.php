<?php

/** Update the About Us CMS page with real DIIDS story content. */

$page = \Webkul\CMS\Models\Page::find(1); // about-us
if (! $page) { echo "about page missing\n"; return; }

$html = <<<'HTML'
<h2>Everyday Confidence</h2>
<p>DIIDS is premium underwear created for people who value confidence, comfort and style every day. Built on the belief that what you wear closest to your skin should feel as good as it looks.</p>
<h3>The Brand</h3>
<p>We combine high-quality fabrics, modern design and exceptional craftsmanship to deliver everyday essentials that elevate comfort without compromising performance. Designed for both men and women, every DIIDS product is made to provide a perfect fit and lasting durability.</p>
<h3>Our Mission</h3>
<p>To redefine everyday comfort by creating premium underwear and essentials that empower people to feel confident, comfortable, and ready. Every piece is thoughtfully designed to deliver unmatched comfort, premium quality, and effortless style, because confidence starts with what you wear underneath.</p>
HTML;

$t = $page->translate('en');
$t->html_content = $html;
$t->page_title = 'Our Story';
$t->meta_title = 'Our Story — DIIDS';
$t->meta_description = 'DIIDS is premium underwear for everyday confidence, comfort and style.';
$t->save();

echo "About Us page updated (Our Story)\n";
