<?php

use App\Models\Message;
use App\Models\Category;
use App\Models\PostTag;
use App\Models\PostCategory;
use App\Models\Order;
use App\Models\Wishlist;
use App\Models\Shipping;
use App\Models\Cart;
use Illuminate\Support\Str;
use App\Helpers\LocationHelper;

class Helper
{
    public static function messageList()
    {
        return Message::whereNull('read_at')->orderBy('created_at', 'desc')->get();
    }

    public static function getAllCategory()
    {
        $category = new Category();
        $menu = $category->getAllParentWithChild();
        return $menu;
    }

    public static function getHeaderCategory()
    {
        $category = new Category();
        $menu = $category->getAllParentWithChild();

        if ($menu) {
?>
            <li>
                <a href="javascript:void(0);">Category<i class="ti-angle-down"></i></a>
                <ul class="dropdown border-0 shadow">
                    <?php
                    foreach ($menu as $cat_info) {
                        if ($cat_info->child_cat->count() > 0) {
                    ?>
                            <li><a href="<?php echo route('product-cat', $cat_info->slug); ?>"><?php echo $cat_info->title; ?></a>
                                <ul class="dropdown sub-dropdown border-0 shadow">
                                    <?php
                                    foreach ($cat_info->child_cat as $sub_menu) {
                                    ?>
                                        <li><a href="<?php echo route('product-sub-cat', [$cat_info->slug, $sub_menu->slug]); ?>"><?php echo $sub_menu->title; ?></a></li>
                                    <?php
                                    }
                                    ?>
                                </ul>
                            </li>
                        <?php
                        } else {
                        ?>
                            <li><a href="<?php echo route('product-cat', $cat_info->slug); ?>"><?php echo $cat_info->title; ?></a></li>
                    <?php
                        }
                    }
                    ?>
                </ul>
            </li>
<?php
        }
    }

    public static function productCategoryList($option = 'all')
    {
        if ($option == 'all') {
            return Category::orderBy('id', 'DESC')->get();
        }
        return Category::has('products')->orderBy('id', 'DESC')->get();
    }

    public static function postTagList($option = 'all')
    {
        if ($option = 'all') {
            return PostTag::orderBy('id', 'desc')->get();
        }
        return PostTag::has('posts')->orderBy('id', 'desc')->get();
    }

    public static function postCategoryList($option = "all")
    {
        if ($option = 'all') {
            return PostCategory::orderBy('id', 'DESC')->get();
        }
        return PostCategory::has('posts')->orderBy('id', 'DESC')->get();
    }

    // Cart Count
    public static function cartCount($user_id = '')
    {
        if (Auth::check()) {
            if ($user_id == "") $user_id = auth()->user()->id;
            return Cart::where('user_id', $user_id)->where('order_id', null)->sum('quantity');
        }
        return 0;
    }

    // Relationship cart with product
    public function product()
    {
        return $this->hasOne('App\Models\Product', 'id', 'product_id');
    }

    public static function getAllProductFromCart($user_id = '')
    {
        if (Auth::check()) {
            if ($user_id == "") $user_id = auth()->user()->id;
            return Cart::with(['product.locationPrices'])
                ->where('user_id', $user_id)
                ->where('order_id', null)
                ->get();
        }
        return [];
    }

    // Total amount cart
    public static function totalCartPrice($user_id = '')
    {
        if (Auth::check()) {
            if ($user_id == "") $user_id = auth()->user()->id;
            $carts = self::getAllProductFromCart($user_id);
            $total = 0;
            foreach ($carts as $cart) {
                $location_price = $cart->product->getPriceForLocation(LocationHelper::getCountryCode()) ?? $cart->product->price;
                $discounted_price = $location_price - ($location_price * ($cart->product->discount ?? 0) / 100);
                $total += $discounted_price * $cart->quantity;
            }
            return $total;
        }
        return 0;
    }

    // Wishlist Count
    public static function wishlistCount($user_id = '')
    {
        if (Auth::check()) {
            if ($user_id == "") $user_id = auth()->user()->id;
            return Wishlist::where('user_id', $user_id)->where('cart_id', null)->sum('quantity');
        }
        return 0;
    }

    public static function getAllProductFromWishlist($user_id = '')
    {
        if (Auth::check()) {
            if ($user_id == "") $user_id = auth()->user()->id;
            return Wishlist::with(['product.locationPrices'])
                ->where('user_id', $user_id)
                ->where('cart_id', null)
                ->get();
        }
        return [];
    }

    public static function totalWishlistPrice($user_id = '')
    {
        if (Auth::check()) {
            if ($user_id == "") $user_id = auth()->user()->id;
            $wishlists = self::getAllProductFromWishlist($user_id);
            $total = 0;
            foreach ($wishlists as $wishlist) {
                $location_price = $wishlist->product->getPriceForLocation(LocationHelper::getCountryCode()) ?? $wishlist->product->price;
                $discounted_price = $location_price - ($location_price * ($wishlist->product->discount ?? 0) / 100);
                $total += $discounted_price * $wishlist->quantity;
            }
            return $total;
        }
        return 0;
    }

    // Total price with shipping and coupon
    public static function grandPrice($id, $user_id)
    {
        $order = Order::find($id);
        if ($order) {
            $shipping_price = (float) ($order->shipping->price ?? 0);
            $order_price = self::orderPrice($id, $user_id);
            return number_format((float) ($order_price + $shipping_price), 2, '.', '');
        }
        return 0;
    }

    // Admin home
    public static function earningPerMonth()
    {
        $month_data = Order::where('status', 'delivered')->get();
        $price = 0;
        foreach ($month_data as $data) {
            foreach ($data->cart_info as $cart) {
                $location_price = $cart->product->getPriceForLocation(LocationHelper::getCountryCode()) ?? $cart->product->price;
                $discounted_price = $location_price - ($location_price * ($cart->product->discount ?? 0) / 100);
                $price += $discounted_price * $cart->quantity;
            }
        }
        return number_format((float) $price, 2, '.', '');
    }

    public static function shipping()
    {
        return Shipping::orderBy('id', 'DESC')->get();
    }
}

if (!function_exists('generateUniqueSlug')) {
    /**
     * Generate a unique slug for a given title and model.
     *
     * @param string $title
     * @param string $modelClass
     * @return string
     */
    function generateUniqueSlug($title, $modelClass)
    {
        $slug = Str::slug($title);
        $count = $modelClass::where('slug', $slug)->count();

        if ($count > 0) {
            $slug = $slug . '-' . date('ymdis') . '-' . rand(0, 999);
        }

        return $slug;
    }
}
?>
