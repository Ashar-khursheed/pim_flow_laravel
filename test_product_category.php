<?php

use Illuminate\Support\Collection;

// Mock classes
class MockCategory {
    public $id;
    public $name;
    public $parent_id;

    public function __construct($id, $name, $parent_id = null) {
        $this->id = $id;
        $this->name = $name;
        $this->parent_id = $parent_id;
    }
}

class MockProduct {
    public $categories;

    public function mostSpecificCategory()
    {
        // Use the loaded collection to avoid DB queries if possible, or lazy load
        $categories = $this->categories;

        if ($categories->isEmpty()) {
            return null;
        }

        // Get all category IDs that are parents to confirmed other categories in this list
        $parentIds = $categories->pluck('parent_id')->filter()->unique();

        // The most specific categories are those whose IDs are NOT in the list of parent IDs
        // i.e., they are not parents to any other assigned category
        $leaves = $categories->whereNotIn('id', $parentIds);

        // If multiple leaves exist, order by ID descending (or created_at if available) to pick the latest
        return $leaves->sortByDesc('id')->first();
    }
}

// Setup basic environment
require 'vendor/autoload.php';

echo "Running Mock Tests...\n";

// Test 1: Parent and Child
$parent = new MockCategory(1, 'Parent');
$child = new MockCategory(2, 'Child', 1);

$product = new MockProduct();
$product->categories = new Collection([$parent, $child]);

$result = $product->mostSpecificCategory();
assert($result->id === 2, "Test 1 Failed: Expected Child (2), got " . ($result->id ?? 'null'));
echo "Test 1 Passed: Parent(1) -> Child(2) selected Child(2)\n";


// Test 2: Grandparent
$gparent = new MockCategory(10, 'Grandparent');
$parent = new MockCategory(11, 'Parent', 10);
$child = new MockCategory(12, 'Child', 11);

$product = new MockProduct();
$product->categories = new Collection([$gparent, $parent, $child]);

$result = $product->mostSpecificCategory();
assert($result->id === 12, "Test 2 Failed: Expected Child (12), got " . ($result->id ?? 'null'));
echo "Test 2 Passed: GParent(10) -> Parent(11) -> Child(12) selected Child(12)\n";


// Test 3: Multiple Branches (should pick highest ID)
$branch1 = new MockCategory(20, 'Branch1');
$branch2 = new MockCategory(30, 'Branch2');

$product = new MockProduct();
$product->categories = new Collection([$branch1, $branch2]);

$result = $product->mostSpecificCategory();
assert($result->id === 30, "Test 3 Failed: Expected Branch2 (30), got " . ($result->id ?? 'null'));
echo "Test 3 Passed: Branch1(20), Branch2(30) selected Branch2(30)\n";
