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
	 * @param bool $withDescription Include description fields
	 * @param bool $withAttributes Include all attributes
	 * @param bool $withTranslation Use translations or direct fields
	 * @return void
	 */
	public function transformFeaturedProduct($product, $categoryMostParentURL = null, $categoryURL = null, $withDescription = false, $withAttributes = false, $withTranslation = true)
	{
		/* Transform product name and images based on translation flag */
		if ($withTranslation) {
			/* Use translations */
			$product->name = $this->getLocalizedData($product->translations, 'name_tr');
			$product->images = $this->getLocalizedData($product->translations, 'images_tr', true);
		} else {
			/* Use direct fields */
			$product->name = $product->name ?? null;
			$product->images = is_array($product->images)
			? $product->images
			: json_decode($product->images, true);
		}

		if ($withDescription) {
			if ($withTranslation) {
				/* Use translations */
				$product->description = $this->getLocalizedData($product->translations, 'description_tr', true);
				$product->benefits_features_tr = $this->getLocalizedData($product->translations, 'benefits_features_tr', true);
			} else {
				/* Use direct fields */
				$product->description = is_array($product->description)
				? $product->description
				: json_decode($product->description, true);
				$product->benefits_features = is_array($product->benefits_features)
				? $product->benefits_features
				: json_decode($product->benefits_features, true);
			}
		}

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

		if ($withAttributes) {
			if ($withTranslation) {
				/* Use translations for attributes */
				$product->all_attributes = $product->productAttributes->map(function ($productAttribute) {
					$attribute = [
						'attribute_name' => $this->getLocalizedData($productAttribute->attributeDetails->translations, 'name_tr'),
						'attribute_value' => $this->getLocalizedData($productAttribute->translations, 'attribute_value_tr')
					];

					if ($productAttribute->measurementUnit) {
						$attribute['measurement_unit'] = $this->getLocalizedData($productAttribute->measurementUnit->translations, 'name_tr');
					}

					return $attribute;
				});
			} else {
				/* Use direct fields for attributes */
				$product->all_attributes = $product->productAttributes->map(function ($productAttribute) {
					$attribute = [
						'attribute_name' => $productAttribute->attributeDetails->name ?? null,
						'attribute_value' => $productAttribute->attribute_value ?? null
					];

					if ($productAttribute->measurementUnit) {
						$attribute['measurement_unit'] = $productAttribute->measurementUnit->name ?? null;
					}

					return $attribute;
				});
			}
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
			$product->productAttributes,
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