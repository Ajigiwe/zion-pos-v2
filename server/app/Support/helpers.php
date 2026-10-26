<?php

use App\Models\Setting;
use App\Support\ShopContext;

if (! function_exists('current_shop')) {
    /** The shop this request operates in (null on console jobs). */
    function current_shop(): ?\App\Models\Shop
    {
        return ShopContext::shop();
    }
}

if (! function_exists('shop_id')) {
    /** Active shop id (null on console jobs — shop-scoped queries then run unscoped). */
    function shop_id(): ?int
    {
        return ShopContext::id();
    }
}

if (! function_exists('money')) {
    /** Format an amount with the configured currency symbol. */
    function money(float|string $amount, ?string $symbol = null): string
    {
        $symbol ??= Setting::get('currency', 'GHS');

        return $symbol.' '.number_format((float) $amount, 2);
    }
}

if (! function_exists('receipt_flag')) {
    /** Receipt toggles are stored as '1'/'0'/'true'/'false'; default only when unset. */
    function receipt_flag(string $key, bool $default): bool
    {
        $raw = Setting::get($key);

        return $raw === null ? $default : in_array(strtolower((string) $raw), ['1', 'true', 'on'], true);
    }
}

if (! function_exists('store_info')) {
    /** Store profile + full receipt customization, mirroring the Flutter app 1:1. */
    function store_info(): array
    {
        return [
            // Store details
            'name' => Setting::get('store.name', 'Untitled Store'),
            'address' => Setting::get('store.address', ''),
            'phone' => Setting::get('store.phone', ''),

            // Header & store branding
            'show_store_name' => receipt_flag('receipt.showStoreName', true),
            'show_store_address' => receipt_flag('receipt.showStoreAddress', true),
            'show_store_phone' => receipt_flag('receipt.showStorePhone', true),
            'header_custom_text' => Setting::get('receipt.headerCustomText',
                'Dealers in Quality Musical Instruments & Audio Equipment'),

            // Item & line breakdown
            'show_cashier_name' => receipt_flag('receipt.showCashierName', true),
            'show_item_price_math' => receipt_flag('receipt.showItemPriceMath', true),
            'show_tax' => receipt_flag('receipt.showTax', true),
            'show_discount' => receipt_flag('receipt.showDiscount', true),

            // Payment & settlement
            'show_payment_breakdown' => receipt_flag('receipt.showPaymentBreakdown', true),
            'show_change_due' => receipt_flag('receipt.showChangeDue', true),

            // Footer, barcode & policy
            'footer_message' => Setting::get('receipt.footerMessage',
                "Goods sold in good condition are not returnable without receipt.\nThank you for your business!"),
            'show_barcode' => receipt_flag('receipt.showBarcode', true),
            'show_customer_signature' => receipt_flag('receipt.showCustomerSignature', false),

            // Paper roll & margin calibration
            'paper_width' => Setting::get('receipt.paperWidth', '80') === '58' ? '58' : '80',
            'side_margin' => (float) (Setting::get('receipt.sideMargin', '14')),
            'bottom_feed_space' => (float) (Setting::get('receipt.bottomFeedSpace', '50')),

            // Legacy keys kept for settings page compatibility
            'footer' => Setting::get('receipt.footerMessage', 'Thank you for your business!'),
            'currency' => Setting::get('currency', 'GHS'),
        ];
    }
}

if (! function_exists('theme_info')) {
    /** Theme mode + accent color (Flutter ThemeSettingKeys: theme_mode/theme_color/theme_preset). */
    function theme_info(): array
    {
        $presets = [
            'emerald' => ['Forest Emerald', '#16A34A'],
            'sapphire' => ['Ocean Sapphire', '#0284C7'],
            'amethyst' => ['Royal Amethyst', '#7C3AED'],
            'amber' => ['Sunset Amber', '#EA580C'],
            'teal' => ['Midnight Teal', '#0D9488'],
            'ruby' => ['Crimson Ruby', '#E11D48'],
            'slate' => ['Monochrome Slate', '#475569'],
        ];

        $mode = Setting::get('theme_mode', 'light'); // light|dark|system
        $preset = Setting::get('theme_preset', 'emerald');
        $color = Setting::get('theme_color', '');

        if ($preset !== '' && $preset !== 'custom' && isset($presets[$preset])) {
            $accent = $presets[$preset][1];
        }

        // Custom hex wins when stored (Flutter stores the resolved 8-digit hex).
        if ($color !== '') {
            $hex = strtoupper(ltrim(str_replace(['#', '0x'], '', $color), '0F'));
            if (strlen($hex) === 8) {
                $hex = substr($hex, 2);
            }
            if (strlen($hex) === 6 && ctype_xdigit($hex)) {
                $accent = '#'.$hex;
            }
        }
        $accent ??= $presets['emerald'][1];

        return [
            'mode' => in_array($mode, ['light', 'dark', 'system'], true) ? $mode : 'light',
            'preset' => $preset !== '' ? $preset : 'emerald',
            'accent' => $accent,
            'presets' => $presets,
        ];
    }
}

if (! function_exists('low_stock_threshold')) {
    /** Global low-stock alert level — products with their own reorder level ignore it. */
    function low_stock_threshold(): float
    {
        $raw = Setting::get('inventory.lowStockThreshold');

        return is_numeric($raw) && (float) $raw >= 0 ? (float) $raw : 5.0;
    }
}

if (! function_exists('barcode_svg')) {
    /**
     * Code128-B barcode as an inline SVG (no external libs — works in DOM and
     * dompdf PDFs). $code is drawn at ~2px wide bars scaled to $width mm.
     */
    function barcode_svg(string $code, int $width = 90): string
    {
        $code = substr(preg_replace('/[^\x20-\x7E]/', '', $code) ?: '0', 0, 48);
        if ($code === '') {
            $code = '0';
        }

        // Code128-B: start code B (104), checksum, stop (106).
        $patterns = require(dirname(__DIR__, 2).'/app/Console/code128_patterns.php');
        $bars = [];
        $modules = 0;
        $sum = 104;

        $emit = function (int $value) use (&$bars, &$modules, $patterns): void {
            $digits = str_split($patterns[$value]);
            $isBar = true;
            foreach ($digits as $d) {
                if ($isBar) {
                    $bars[] = [$modules, (int) $d];
                }
                $modules += (int) $d;
                $isBar = ! $isBar;
            }
        };

        foreach (str_split($code) as $i => $char) {
            $value = ord($char) - 32;
            $sum += ($i + 1) * max(0, min(102, $value));
            $emit($value);
        }
        $emit($sum % 103); // check digit
        $emit(106);        // stop pattern

        $rects = '';
        foreach ($bars as [$offset, $barWidth]) {
            if ($barWidth < 1) {
                continue;
            }
            $rects .= sprintf('<rect x="%d" y="0" width="%d" height="40"/>', $offset, $barWidth);
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$modules.' 40" '
            .'preserveAspectRatio="none" style="width:'.$width.'mm;height:10mm" '
            .'shape-rendering="crispEdges" fill="#000">'.$rects.'</svg>';
    }
}
