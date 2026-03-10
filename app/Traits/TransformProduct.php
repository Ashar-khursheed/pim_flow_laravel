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
	public function transformFeaturedProduct($product, $categoryMostParentURL = null, $categoryURL = null, $withDescription = false, $withAttributes = false, $withTranslation = true, $wishlistProductIds = [])
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

		$product->parent_category_url = ($categoryMostParentURL !== null) ? $categoryMostParentURL : $product->parent_category_url();
$product->category_url = ($categoryURL !== null) ? $categoryURL : $product->category_url();
		$product->url = optional($product->seoUrl)->url ?? null;
		$product->quote_available = $product->quote_available ?? null;

		/* Currency data */
		$product->currency_name = optional($product->currency)->title ?? null;
		$product->currency_symbol = optional($product->currency)->symbol ?? null;

		/* Reviews data */
		$product->total_reviews = $product->reviews_count ?? null;
		$avgRating = $product->reviews_avg_star !== null ? (float) $product->reviews_avg_star : null;
		$product->avg_rating = $avgRating !== null ? round($avgRating, 1) : null;

		/* Alt tags */
		$product->alt_tags = is_array($product->alt_tags) ? $product->alt_tags : json_decode($product->alt_tags, true) ?? [];

		/* Basic product fields */
		$videoPaths = is_array($product->video_path) ? $product->video_path : json_decode($product->video_path, true);
		$product->video_path = collect($videoPaths)->map(fn($v) => $v);
		$product->sku = $product->sku;
		$product->start_date = $product->start_date;
		$product->end_date = $product->end_date;
		$product->isRequired = $product->isRequired;

		/* Stock logic */
		$quantity = $product->quantity ?? null;
		$unitsSold = $product->units_sold ?? null;
		$product->leftStock = ($quantity !== null && $unitsSold !== null) ? ($quantity - $unitsSold) : null;

		/* Wishlist logic */
		$product->in_wishlist = in_array($product->id, $wishlistProductIds);

		/* Supplier and Price Data */
		$firstSupplier = $product->productSuppliers ? $product->productSuppliers->first() : null;

		if ($firstSupplier) {
			$product->vendor_sku = $firstSupplier->vendor_sku ?? null;
			$product->vendor_id = $firstSupplier->vendor_id ?? null;
			$product->vendor_country = $firstSupplier->vendor->country->name ?? null;
			$product->vendor_city = $firstSupplier->vendor->city->name ?? null;
			$product->vendor_address = $firstSupplier->vendor->address ?? null;
			$product->vendor_zipcode = $firstSupplier->vendor->zipcode ?? null;

			$product->price = $firstSupplier->price !== null ? (float) $firstSupplier->price : null;
			$product->sale_price = $firstSupplier->sale_price !== null ? (float) $firstSupplier->sale_price : null;
			$product->original_price = $product->price;
			$product->front_sale_price = $product->sale_price ?: $product->price;
			$product->best_price = $product->price;

			$product->map = $firstSupplier->map !== null ? (float) $firstSupplier->map : null;
			$product->inventory = $firstSupplier->inventory ?? null;
			$product->in_stock = $firstSupplier->in_stock ?? null;
			$product->delivery_days = $firstSupplier->delivery_days ?? null;
			$product->return_policy = $firstSupplier->return_policy ?? null;
			$product->free_shipping = $firstSupplier->free_shipping ?? null;
			$product->warranty_information = $firstSupplier->warranty_information ?? null;
			$product->min_quantity = $firstSupplier->min_quantity ?? null;
			$product->is_fixed = $firstSupplier->is_fixed ?? null;
		} else {
			$product->price = $product->price !== null ? (float) $product->price : null;
			$product->sale_price = $product->sale_price !== null ? (float) $product->sale_price : null;
			$product->original_price = $product->price;
			$product->front_sale_price = $product->sale_price ?: $product->price;
			$product->best_price = $product->price;
		}

		$currency = $product->currency;
		$product->currency = $product->currency_symbol;
		$product->currency_title = $currency
		? ($currency->is_prefix_symbol
			? $product->currency_symbol . ' ' . $product->price
			: $product->price . ' ' . $product->currency_symbol)
		: $product->price;

		/* Selling unit and Per unit price logic */
		$sellingType = null;
		if ($product->sellingUnitAttribute) {
			$attributeValue = $product->sellingUnitAttribute->attribute_value;
			$product->selling_type_value = $attributeValue;
			$unit = strpos($attributeValue, '/') !== false
			? trim(explode('/', $attributeValue)[1])
			: $attributeValue;
			$product->selling_type_unit = $unit;
			$sellingType = [
				'attribute_value' => $attributeValue,
				'attribute_value_unit' => $unit,
			];
		} else {
			$product->selling_type_value = null;
			$product->selling_type_unit = null;
		}
		$product->selling_type = $sellingType;

		$perUnitPrice = null;
		if ($product->productAttributes) {
			$unitsPerCase = $product->productAttributes->firstWhere(fn($attr) => $attr->attributeDetails?->name === 'Units per Case');
			$packType = $product->productAttributes->firstWhere(fn($attr) => $attr->attributeDetails?->name === 'Pack Type');

			$basePrice = ($product->sale_price > 0) ? $product->sale_price : $product->price;
			if ($basePrice && $unitsPerCase && is_numeric($unitsPerCase->attribute_value)) {
				$unitValue = (float) $unitsPerCase->attribute_value;
				if ($unitValue > 0) {
					$calculated = round($basePrice / $unitValue, 2);
					$perUnitPrice = $calculated . ' /' . ($packType?->attribute_value ?? '');
				}
			}
		}
		$product->per_unit_price = $perUnitPrice;

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