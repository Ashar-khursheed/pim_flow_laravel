<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TempProductStatus;

class TempProductStatusSeeder extends Seeder
{
	public function run(): void
	{
		$statuses = [
			['Pending Enrichment', 'pending_enrichment'],
			['Attribute in Progress', 'attribute_in_progress'],
			['Pending Attribute Review', 'pending_attribute_review'],
			['Attribute Locked', 'attribute_locked'],
			['Pending Content', 'pending_content'],
			['Content in Progress', 'content_in_progress'],
			['Content Locked', 'content_locked'],
			['Design Pending', 'design_pending'],
			['Design in Progress', 'design_in_progress'],
			['Design Locked', 'design_locked'],
			['SEO Pending', 'seo_pending'],
			['SEO in Progress', 'seo_in_progress'],
			['SEO Locked', 'seo_locked'],
			['AI Review Pending', 'ai_review_pending'],
			['AI Review Passed', 'ai_review_passed'],
			['Pre-live Approval Pending', 'prelive_approval_pending'],
			['Live', 'live'],
			['Live (Updated)', 'live_updated'],
		];

		foreach ($statuses as $index => [$name, $code]) {
			TempProductStatus::updateOrCreate(
				['code' => $code],
				['name' => $name, 'step_number' => $index + 1]
			);
		}
	}
}
