<?php

namespace App\Cms;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Typed public catalogue layout; inherited source settings never override live POS stock authority. */
final class CataloguePresentation
{
    public const DEFAULTS = [
        'grid_desktop' => 3, 'grid_tablet' => 2, 'grid_mobile' => 1,
        'image_ratio' => '4:3', 'card_density' => 'comfortable',
        'items_per_page' => 12, 'default_sort' => 'oldest', 'badge_behavior' => 'status_pill',
        'show_brand' => true, 'show_specs' => true, 'show_colors' => true,
        'show_warranty' => true, 'show_compare' => true, 'show_out_of_stock' => true,
    ];

    private const CHOICES = [
        'grid_desktop' => [3, 4, 5, 6], 'grid_tablet' => [2, 3, 4],
        'grid_mobile' => [1, 2], 'image_ratio' => ['16:9', '4:3', '1:1'],
        'card_density' => ['compact', 'comfortable'], 'items_per_page' => [12, 24, 36, 48],
        'default_sort' => ['newest', 'oldest', 'price_asc', 'price_desc', 'name_asc', 'name_desc'],
        'badge_behavior' => ['status_text', 'status_pill', 'hidden'],
    ];

    public function normalize(mixed $input): array
    {
        abort_unless(is_array($input) && ! array_is_list($input)
            && count($input) === count(self::DEFAULTS)
            && array_diff(array_keys($input), array_keys(self::DEFAULTS)) === [], 422,
            'Submit exactly the 14 typed catalogue settings.');
        foreach (self::DEFAULTS as $key => $default) {
            $value = $input[$key];
            if (array_key_exists($key, self::CHOICES)) {
                abort_unless(in_array($value, self::CHOICES[$key], true), 422,
                    'Unsupported catalogue presentation option: '.$key);
            } else {
                abort_unless(is_bool($value), 422, 'Catalogue visibility requires a boolean: '.$key);
            }
        }
        // The current W02 contract requires published out-of-stock items to remain discoverable.
        abort_unless($input['show_out_of_stock'] === true, 422,
            'Published zero-stock products must remain discoverable; this setting cannot hide them.');

        return array_replace(self::DEFAULTS, $input);
    }

    public function publicValues(): array
    {
        $json = DB::table('site_settings')->where('key', 'cms.presentation.catalogue')->value('value');
        if (! is_string($json)) {
            return self::DEFAULTS;
        }
        $values = json_decode($json, true);
        if (! is_array($values)) {
            return self::DEFAULTS;
        }
        try {
            return $this->normalize($values);
        } catch (HttpException $exception) {
            // Historical untyped snapshots cannot become public presentation authority.
            return self::DEFAULTS;
        }
    }
}
