<?php

namespace App\Observers;

use Illuminate\Support\Arr;
use App\Models\TransactionLog;

class TransactionLogObserver
{
	public function created($model)
	{
		$this->createLog($model, __('lbl_add'));
	}

	public function updating($model)
	{
		// dd(request()->all(), $model->attributeValues->toArray(), class_basename($model));
	}

	public function updated($model)
	{
		$this->createLog($model, __('lbl_edit'));
	}

	public function deleted($model)
	{
		$this->createLog($model, __('lbl_dlt'));
	}

	private function createLog($model, $action)
	{
		$module = class_basename($model);
		$identifier = $model->id;
		$changeObj = null;
		$description = null;

		if ($action == __('lbl_edit')) {
			$changedField = Arr::except($model->getChanges(), ['updated_at']);

			if (count($changedField)) {
				$oldArray = [];
				$newArray = [];
				$oldData = $model->getOriginal();

				foreach ($changedField as $key => $value) {
					if ($module == 'Attribute' && $key == 'attribute_group_id') {
						$oldGroupId = $oldData['attribute_group_id'] ?? null;
						$oldArray['attribute_group'] = optional(\App\Models\AttributeGroup::find($oldGroupId))->name;
						$newArray['attribute_group'] = optional($model->attributeGroup)->name;
					} else {
						$oldArray[$key] = $oldData[$key] ?? null;
						$newArray[$key] = $value;
					}
				}

				$changes = [
					'old_value' => $oldArray,
					'new_value' => $newArray,
				];

				$changeObj = json_encode($changes);
			}
		}

		if ($action == __('lbl_dlt')) {
			$changes = [
				"value" => Arr::except($model->getOriginal(), ['id', 'password', 'created_at', 'updated_at'])
			];
			$changeObj = json_encode($changes);
		}

		if ($action == __('lbl_add')) {
			$changes = [
				"value" => Arr::except($model->toArray(), ['id', 'password', 'created_at', 'updated_at'])
			];
			$changeObj = json_encode($changes);
		}
		if ($changeObj) {
			$log = new TransactionLog();
			$log->module = $module;
			$log->action = $action;
			$log->identifier = $identifier;
			$log->change_obj = $changeObj;
			$log->description = $description;
			$log->created_by = ($module === 'Product') ? \App\Models\Product::$observerUserId : (auth()->id() ?? null);
			$log->created_at = now();
			$log->save();
		}
	}
}