<?php

namespace App\Traits;

use App\Models\FrontEnd\Quote;
use Carbon\Carbon;

trait TransformProduct
{
	/**
	 * Transform featured product with category URLs
	 *
	 * @param object $product Product model instance
	 * @param string|null $categoryMostParentURL Parent category URL
	 * @param string|null $categoryURL Category URL
	 * @return void
	 */
	public function transformFeaturedProduct($product, $categoryMostParentURL = null, $categoryURL = null)
	{
		/* Transform product name and images to locale objects */
		$product->name = $this->getLocalizedData($product->translations, 'name_tr');
		$product->images = $this->getLocalizedData($product->translations, 'images_tr', true);
		$product->parent_category_url = $categoryMostParentURL ?? $product->parent_category_url();
		$product->category_url = $categoryURL ?? $product->category_url();
		$product->url = optional($product->seoUrl)->url ?? null;
		$product->quote_available = $product->quote_available ?? 0;

		/* Currency data */
		$product->currency_name = optional($product->currency)->title ?? null;
		$product->currency_symbol = optional($product->currency)->symbol ?? null;

		/* Reviews data */
		$product->total_reviews = $product->reviews_count ?? 0;
		$product->avg_rating = round($product->reviews_avg_star ?? 0, 1);

		/* Alt tags */
		$product->alt_tags = is_array($product->alt_tags) ? $product->alt_tags : json_decode($product->alt_tags, true) ?? [];

		/* Selling unit data */
		if ($product->sellingUnitAttribute) {
			$attributeValue = $product->sellingUnitAttribute->attribute_value;
			$product->selling_type_value = $attributeValue;
			$product->selling_type_unit = strpos($attributeValue, '/') !== false
			? trim(explode('/', $attributeValue)[1])
			: $attributeValue;
		} else {
			$product->selling_type_value = null;
			$product->selling_type_unit = null;
		}

		/* Transform product suppliers */
		if ($product->productSuppliers) {
			$product->productSuppliers->each(function ($productSupplier) {
				unset($productSupplier->id);
				unset($productSupplier->product_id);
			});
		}

		/* Remove unwanted attributes from product */
		unset(
			$product->translations,
			$product->seoUrl,
			$product->currency,
			$product->currency_id,
			$product->reviews,
			$product->reviews_count,
			$product->reviews_avg_star,
			$product->sellingUnitAttribute,
			$product->pivot
		);
	}

	/**
	 * Get localized data from translations
	 *
	 * @param object $translations Collection of translations
	 * @param string $field Field name to extract (e.g., 'name_tr', 'images_tr')
	 * @param bool $parseJson Whether to parse JSON for the field
	 * @return array Localized data
	 */
	public function getLocalizedData($translations, $field, $parseJson = false)
	{
		$data = [];

		if ($translations) {
			foreach ($translations as $translation) {
				$value = $translation->$field ?? null;

				if ($parseJson && $value) {
					$data[$translation->locale] = is_array($value)
						? $value
						: json_decode($value, true);
				} else {
					$data[$translation->locale] = $value;
				}
			}
		}

		return $data;
	}
}
