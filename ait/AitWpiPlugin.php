<?php
declare(strict_types=1);

use Hyyan\WPI\Tools\FlashMessages;
use Hyyan\WPI\MessagesInterface;

class AitWpiPlugin extends Hyyan\WPI\Plugin
{
    public static function canActivate(): bool
    {
        return (isset($GLOBALS['polylang']) && $GLOBALS['polylang'] && defined('WOOCOMMERCE_VERSION'));
    }

    public function activate(): void
    {
        if (self::canActivate()) {
            $this->registerCore();
        }
    }

    protected function registerCore(): void
    {
        new Hyyan\WPI\Emails();
        new Hyyan\WPI\Cart();
        new Hyyan\WPI\Login();
        new Hyyan\WPI\Order();
        new Hyyan\WPI\Pages();
        new Hyyan\WPI\Endpoints();
        new Hyyan\WPI\Product\Product();
        new Hyyan\WPI\Taxonomies\Taxonomies();
        new Hyyan\WPI\Media();
        new Hyyan\WPI\Permalinks();
        new Hyyan\WPI\Language();
        new Hyyan\WPI\Coupon();
        new Hyyan\WPI\Reports();
        new Hyyan\WPI\Widgets\SearchWidget();
        new Hyyan\WPI\Widgets\LayeredNav();
        new Hyyan\WPI\Gateways();
        new Hyyan\WPI\Shipping();
        new Hyyan\WPI\Breadcrumb();
    }
}